/**
 * webview_bridge.m — libui ↔ WebView embed bridge (macOS)
 *
 * Problem:  webview_create() calls [window setContentView:wkWebView] which
 *           replaces the entire content view — killing all libui controls.
 *
 * Solution: Create a borderless CHILD NSWindow positioned at the desired
 *           "iframe" area within the libui window, and create the webview
 *           inside that child window.  The child window floats above libui's
 *           content, receives its own events, and is clipped to its frame.
 *
 *           ┌─ libui NSWindow ────────────────────────────────┐
 *           │  ┌─ libui VBox/HBox ──────────────────────────┐ │
 *           │  │  [Sidebar]  [Label placeholder]            │ │
 *           │  └────────────────────────────────────────────┘ │
 *           │  ┌─ child NSWindow (borderless, no titlebar) ─┐ │
 *           │  │  WKWebView (contentView)                    │ │
 *           │  │  ← webview_set_html() / navigate()         │ │
 *           │  └────────────────────────────────────────────┘ │
 *           └─────────────────────────────────────────────────┘
 *
 * All webview control functions (set_html, navigate, bind, eval...)
 * are called directly on PebView.dylib via FFI — the bridge only
 * handles creation, positioning, and destruction.
 *
 * Coordinate system:
 *   PHP passes (x, y) from top-left of the libui content view.
 *   The bridge detects flipped/unflipped and converts to screen coords.
 *
 * Compile:
 *   clang -shared -fobjc-arc                      \
 *         webview_bridge.m                         \
 *         vendor/kingbes/pebview/lib/macos/arm64/PebView.dylib \
 *         -framework Cocoa                         \
 *         -o webview_bridge.dylib
 */

#import <Cocoa/Cocoa.h>

// ---------------------------------------------------------------------------
// Borderless NSWindow subclass that can become key/main window.
//
// NSWindow with NSWindowStyleMaskBorderless returns NO for
// canBecomeKeyWindow by default, which means makeKeyAndOrderFront:
// silently fails — the WKWebView inside the child window never
// receives keyboard events. This subclass overrides both to return YES.
// ---------------------------------------------------------------------------
@interface KeyableWindow : NSWindow
@end
@implementation KeyableWindow
- (BOOL)canBecomeKeyWindow { return YES; }
- (BOOL)canBecomeMainWindow { return YES; }
@end

// ---------------------------------------------------------------------------
// PebView/webview C API — symbols resolved by linking against PebView.dylib
// ---------------------------------------------------------------------------
extern void *webview_create(int debug, void *window);
extern void *webview_get_window(void *wv);
extern int   webview_destroy(void *wv);

// ---------------------------------------------------------------------------
// Bridge API — all functions are plain C, callable via PHP FFI
// ---------------------------------------------------------------------------

/**
 * wvb_create — Create an embedded webview within a libui window.
 *
 * @param debug              Non-zero to enable Web Inspector.
 * @param parent_handle      Result of uiControlHandle(libui_control).
 *                           For a uiWindow this is the NSWindow*.
 *                           Any handle is accepted (NSView* or NSWindow*).
 * @param x, y               Top-left corner in the PARENT view's coordinates
 *                           (top-left origin, like CSS).
 * @param w, h               Width and height in points.
 * @return webview_t pointer, or NULL on failure.
 *
 * The caller retains ownership of the returned webview_t and MUST call
 * wvb_destroy() before the libui window is destroyed.
 */
__attribute__((visibility("default")))
void *wvb_create(int debug, uintptr_t parent_handle,
                 int x, int y, int w, int h) {
    @autoreleasepool {
        // uiControlHandle() returns NSWindow* for uiWindow, NSView* for others.
        // Accept both — extract the contentView if given an NSWindow.
        id parentObj = (__bridge id)(void *)parent_handle;
        NSView *parentView = nil;

        if ([parentObj isKindOfClass:[NSView class]]) {
            parentView = (NSView *)parentObj;
        } else if ([parentObj isKindOfClass:[NSWindow class]]) {
            parentView = [(NSWindow *)parentObj contentView];
        } else {
            return NULL;
        }

        NSWindow *parentWindow = [parentView window];
        if (!parentWindow) {
            return NULL;  // parent not yet in a window hierarchy
        }

        NSRect parentBounds = [parentView bounds];

        // Convert from caller's top-left origin to the view's native coords
        CGFloat nativeY = [parentView isFlipped]
            ? (CGFloat)y
            : (NSHeight(parentBounds) - (CGFloat)y - (CGFloat)h);

        NSRect viewRect = NSMakeRect((CGFloat)x, nativeY, (CGFloat)w, (CGFloat)h);

        // View coords → window coords → screen coords
        NSRect windowed = [parentView convertRect:viewRect toView:nil];
        windowed.origin = [parentWindow convertRectToScreen:windowed].origin;

        // Create a borderless child KeyableWindow
        NSWindow *childWin = [[KeyableWindow alloc]
            initWithContentRect:windowed
                     styleMask:NSWindowStyleMaskBorderless
                       backing:NSBackingStoreBuffered
                         defer:NO];
        [childWin setAcceptsMouseMovedEvents:YES];
        [childWin setIgnoresMouseEvents:NO];
        [parentWindow addChildWindow:childWin ordered:NSWindowAbove];
        [childWin makeKeyAndOrderFront:nil];

        // Create the webview — it will set the WKWebView as contentView
        void *wv = webview_create(debug, (__bridge void *)childWin);
        if (!wv) {
            [parentWindow removeChildWindow:childWin];
            [childWin close];
            return NULL;
        }

        return wv;
    }
}

/**
 * wvb_move — Reposition/resize an embedded webview.
 *
 * Call this when the libui layout changes (e.g. window resize) to keep
 * the webview iframe aligned with its placeholder area.
 */
__attribute__((visibility("default")))
void wvb_move(void *wv, uintptr_t parent_handle,
              int x, int y, int w, int h) {
    @autoreleasepool {
        id parentObj = (__bridge id)(void *)parent_handle;
        NSView *parentView = nil;
        if ([parentObj isKindOfClass:[NSView class]]) {
            parentView = (NSView *)parentObj;
        } else if ([parentObj isKindOfClass:[NSWindow class]]) {
            parentView = [(NSWindow *)parentObj contentView];
        } else {
            return;
        }

        NSWindow *parentWindow = [parentView window];
        if (!parentWindow) return;

        NSWindow *childWin = (__bridge NSWindow *)webview_get_window(wv);
        if (!childWin) return;

        NSRect parentBounds = [parentView bounds];

        CGFloat nativeY = [parentView isFlipped]
            ? (CGFloat)y
            : (NSHeight(parentBounds) - (CGFloat)y - (CGFloat)h);

        NSRect viewRect = NSMakeRect((CGFloat)x, nativeY, (CGFloat)w, (CGFloat)h);
        NSRect windowed = [parentView convertRect:viewRect toView:nil];
        windowed.origin = [parentWindow convertRectToScreen:windowed].origin;

        [childWin setFrame:windowed display:YES];
    }
}

/**
 * wvb_set_dock_icon — Set the macOS Dock icon from a file path.
 *
 * Accepts any format NSImage supports (png, icns, jpg, ico).
 * Call this once before or after the window is shown.
 */
__attribute__((visibility("default")))
void wvb_set_dock_icon(const char *iconFilePath) {
    @autoreleasepool {
        if (!iconFilePath || !*iconFilePath) return;
        NSString *path = [NSString stringWithUTF8String:iconFilePath];
        NSImage *image = [[NSImage alloc] initWithContentsOfFile:path];
        if (image) {
            [NSApp setApplicationIconImage:image];
        }
    }
}

/**
 * wvb_destroy — Destroy an embedded webview and clean up the child window.
 *
 * 1. webview_destroy() — WKWebView released, contentView set to nil
 * 2. Remove the child window from its parent
 * 3. Close and release the child window
 *
 * Each step runs in its own @autoreleasepool to ensure autoreleased
 * objects are cleaned up immediately, preventing stale references from
 * lingering in the caller's autorelease pool (which may be the
 * NSApplication run loop's pool — draining it later would crash).
 */
__attribute__((visibility("default")))
void wvb_destroy(void *wv) {
    if (!wv) return;

    // Step 1: Get the child window pointer before destroying the webview.
    // webview_get_window() returns m_window, which becomes nullptr in the
    // destructor — must call before webview_destroy().
    NSWindow *childWin = nil;
    @autoreleasepool {
        void *winPtr = webview_get_window(wv);
        if (winPtr) {
            childWin = (__bridge NSWindow *)winPtr;
        }
    }

    if (!childWin) {
        // No child window — just destroy the webview
        @autoreleasepool {
            webview_destroy(wv);
        }
        return;
    }

    // Step 2: Detach child window from parent BEFORE destroying the webview.
    // This prevents the parent window from referencing a child whose
    // contentView (WKWebView) is about to be released.
    @autoreleasepool {
        NSWindow *parentWin = [childWin parentWindow];
        if (parentWin) {
            [parentWin removeChildWindow:childWin];
        }
    }

    // Step 3: Destroy the webview (C++ delete → ~cocoa_wkwebview_engine).
    // The destructor releases WKWebView, WKWebViewConfiguration, delegates,
    // etc. Running this in its own @autoreleasepool ensures any objects
    // autoreleased during destruction are immediately cleaned up, rather
    // than lingering in the caller's pool where they'd become stale.
    @autoreleasepool {
        webview_destroy(wv);
    }

    // Step 4: Close the child window. Under ARC, closing the window sends
    // windowWillClose: and posts NSWindowWillCloseNotification. Doing this
    // after webview_destroy() ensures the WKWebView (previously the
    // contentView) is already gone and can't receive stray events.
    @autoreleasepool {
        [childWin close];
    }
    // childWin is released by ARC when this function returns (local strong
    // reference drops to 0 after removeChildWindow removed the parent's retain).
}
