#include <Foundation/Foundation.h>
#include <AppKit/AppKit.h>
#include <objc/runtime.h>

// ── Global state (declared early for IMENSTextView reference) ─────────────

static NSTextView* g_ime_text_view = nil;

// Tab callback: PHP provides this for focus navigation.
// Receives (int is_shift_tab): 1 = Shift+Tab, 0 = Tab.
typedef void (*ime_tab_callback_t)(int is_shift_tab);
static ime_tab_callback_t g_ime_tab_callback = NULL;

// Text change callback: PHP provides this. Called by the NSTextViewDidChangeNotification observer.
// Receives the full text (UTF-8, null-terminated) and the caret position (character offset).
typedef void (*ime_notify_callback_t)(const char* text, int caret_position);
static ime_notify_callback_t g_ime_notify_callback = NULL;

// Flag to suppress notify callback during programmatic text changes.
static BOOL g_ime_suppress_notify = NO;

// Observer tokens for block-based NSNotification observers (returned by addObserverForName:).
// Kept to remove observers in ime_destroy_textview.
static NSObject* g_ime_changed_observer = nil;
static NSObject* g_ime_textchanged_observer = nil;

// Forward declaration (static, defined later)
static void ime_textViewDidChange(NSNotification* notification);

// ── Custom NSTextView subclass for IME ────────────────────────────────────
// Overrides keyDown: to handle Tab/Shift+Tab as focus navigation instead of
// inserting tab characters. Other keys pass through to super.

@interface IMENSTextView : NSTextView
@end

@implementation IMENSTextView

- (void)keyDown:(NSEvent*)event {
    NSUInteger keyCode = [event keyCode];
    // 48 = Tab, 52 = Shift+Tab
    if (keyCode == 48 || keyCode == 52) {
        if (g_ime_tab_callback) {
            g_ime_tab_callback(keyCode == 52); // is_shift_tab
            return; // Don't call super — we handled it
        }
    }
    // All other keys: pass through to NSTextView's normal handling
    [super keyDown:event];
}

@end

// ── NSTextView creation / destruction ─────────────────────────────────────

// Create the global NSTextView, add it as a child of the Area's NSClipView,
// and configure it for transparent, IME-capable text input.
//
// Parameters:
//   scroll_view_ns_view  — the NSScrollView (Area NSView) whose documentView
//                          (an NSClipView) will contain the NSTextView.
//   x, y, w, h           — frame of the NSTextView in the NSClipView's coords.
//                          This should match the TextArea's inner content rect
//                          (accounting for the TextAreaRenderer's padding).
//   initial_text         — initial text content (UTF-8, null-terminated).
void ime_create_textview(void* area_ns_view, double x, double y, double w, double h, const char* initial_text) {
    // Destroy any existing text view first
    if (g_ime_text_view) {
        [g_ime_text_view removeFromSuperview];
        g_ime_text_view = nil;
        g_ime_notify_callback = NULL;
        g_ime_tab_callback = NULL;
    }

    NSView* areaView = (__bridge NSView*)area_ns_view;

    // Create NSTextView at the TextArea's content rect. This positions the
    // IME popup and the (invisible) text at the correct location within the
    // TextArea so the user sees input aligned with the rendered text.
    IMENSTextView* textView = [[IMENSTextView alloc] initWithFrame:NSMakeRect(x, y, w, h)];
    [textView setFont:[NSFont systemFontOfSize:13.0]];
    [textView setDrawsBackground:NO];
    [textView setRichText:NO];
    [textView setVerticallyResizable:NO];
    [textView setHorizontallyResizable:NO];
    [textView setMaxSize:NSMakeSize(MAXFLOAT, MAXFLOAT)];
    [textView setMinSize:NSMakeSize(0, 0)];
    [textView setSelectable:YES];
    [textView setEditable:YES];
    [textView setAllowsUndo:NO];

    // Set initial text
    NSString* initialStr = [NSString stringWithUTF8String:initial_text];
    NSTextStorage* ts = [textView textStorage];
    [ts setAttributedString:[[NSAttributedString alloc] initWithString:initialStr]];
    [textView setSelectedRange:NSMakeRange([initialStr length], 0)];

    // Add NSTextView as a subview of the Area NSView (on top)
    [areaView addSubview:textView];
    [areaView setWantsLayer:YES];

    g_ime_text_view = textView;

    // Set up text change notification observers using blocks (avoids selector dispatch issues).
    // Use weak reference to textView to avoid retain cycles.
    NSNotificationCenter* nc = [NSNotificationCenter defaultCenter];
    __weak IMENSTextView* weakTV = textView;
    NSNotificationName changedName = @"NSTextViewDidChangeNotification";
    NSNotificationName textChangedName = @"NSTextDidChangeNotification";
    g_ime_changed_observer = [nc addObserverForName:changedName
                          object:textView
                           queue:NULL
                      usingBlock:^(NSNotification* _Nonnull notification) {
        IMENSTextView* tv = weakTV;
        if (!tv) return;
        ime_textViewDidChange(notification);
    }];
    g_ime_textchanged_observer = [nc addObserverForName:textChangedName
                          object:textView
                           queue:NULL
                      usingBlock:^(NSNotification* _Nonnull notification) {
        IMENSTextView* tv = weakTV;
        if (!tv) return;
        ime_textViewDidChange(notification);
    }];
}

// The NSTextViewDidChangeNotification handler.
// Calls the notify callback with the current text and caret position.
static void ime_textViewDidChange(NSNotification* notification) {
    NSLog(@"[IME] ime_textViewDidChange called, text_view=%p callback=%p suppress=%d", (__bridge void*)g_ime_text_view, (void*)g_ime_notify_callback, g_ime_suppress_notify);
    if (g_ime_suppress_notify || !g_ime_text_view) {
        NSLog(@"[IME] early return: suppress=%d text_view=%p", g_ime_suppress_notify, (__bridge void*)g_ime_text_view);
        return;
    }
    if (!g_ime_notify_callback) {
        NSLog(@"[IME] early return: no callback");
        return;
    }
    NSTextView* textView = g_ime_text_view;
    NSString* text = [textView.textStorage string];
    NSRange selectedRange = [textView selectedRange];
    int caret = (int)selectedRange.location;
    NSLog(@"[IME] calling callback with text=\"%@\" (len=%lu) caret=%d", text, (unsigned long)[text length], caret);

    const char* utf8 = [text UTF8String];
    g_ime_notify_callback(utf8, caret);
}

void ime_set_notify_callback(ime_notify_callback_t callback) {
    NSLog(@"[IME] ime_set_notify_callback: %p", (void*)callback);
    g_ime_notify_callback = callback;
}

void ime_clear_notify_callback(void) {
    NSLog(@"[IME] ime_clear_notify_callback");
    g_ime_notify_callback = NULL;
}

// Set the Tab/Shift+Tab callback (for focus navigation from the IME text view).
// Receives (int is_shift_tab): 1 = Shift+Tab, 0 = Tab.
void ime_set_tab_callback(ime_tab_callback_t callback) {
    g_ime_tab_callback = callback;
}

void ime_clear_tab_callback(void) {
    g_ime_tab_callback = NULL;
}

// Destroy the global NSTextView, remove notification observer, and nil it.
void ime_destroy_textview(void) {
    if (!g_ime_text_view) {
        return;
    }
    NSNotificationCenter* nc = [NSNotificationCenter defaultCenter];
    if (g_ime_changed_observer) {
        [nc removeObserver:g_ime_changed_observer];
        g_ime_changed_observer = nil;
    }
    if (g_ime_textchanged_observer) {
        [nc removeObserver:g_ime_textchanged_observer];
        g_ime_textchanged_observer = nil;
    }
    [g_ime_text_view removeFromSuperview];
    g_ime_text_view = nil;
    g_ime_notify_callback = NULL;
    g_ime_tab_callback = NULL;
    g_ime_suppress_notify = NO;
}

// Get the global NSTextView pointer (for use in callbacks / queries).
void* ime_get_textview(void) {
    return (__bridge void*)g_ime_text_view;
}

// Check if the global NSTextView exists and is non-nil.
int ime_has_textview(void) {
    return g_ime_text_view != nil ? 1 : 0;
}

// ── Text manipulation ─────────────────────────────────────────────────────

// Get the full text of the global NSTextView.
// Returns a CFStringRef (toll-free bridge, caller must CFRelease).
// Returns NULL if no NSTextView exists.
CFStringRef ime_get_text(void) {
    if (!g_ime_text_view) {
        return NULL;
    }
    return (__bridge CFStringRef)[g_ime_text_view.textStorage string];
}

// Set the text of the global NSTextView.
// Suppresses the notify callback during this change.
// Sets caret to end of text.
void ime_set_text(const char* text) {
    if (!g_ime_text_view) {
        return;
    }
    g_ime_suppress_notify = YES;
    NSString* str = [NSString stringWithUTF8String:text];
    NSTextStorage* ts = [g_ime_text_view textStorage];
    [ts setAttributedString:[[NSAttributedString alloc] initWithString:str]];
    [g_ime_text_view setSelectedRange:NSMakeRange([str length], 0)];
    g_ime_suppress_notify = NO;
}

// Get the caret (cursor) position of the global NSTextView.
// Returns the character offset (0-based) of the text selection.
// For a caret (no selection), this is the insertion point.
int ime_get_caret_position(void) {
    if (!g_ime_text_view) {
        return 0;
    }
    NSRange selectedRange = [g_ime_text_view selectedRange];
    return (int)selectedRange.location;
}

// Set the caret (cursor) position of the global NSTextView.
// Moves the insertion point to the given character offset.
// Suppresses the notify callback during this change.
void ime_set_caret_position(int pos) {
    if (!g_ime_text_view) {
        return;
    }
    g_ime_suppress_notify = YES;
    [g_ime_text_view setSelectedRange:NSMakeRange(pos, 0)];
    g_ime_suppress_notify = NO;
}

// Make the global NSTextView the first responder.
// This is the critical step — without it, keyboard events go to the
// Surface Area instead of the NSTextView.
int ime_make_textview_first_responder(void) {
    if (!g_ime_text_view) {
        return 0;
    }
    NSWindow* window = [g_ime_text_view window];
    if (!window) {
        return 0;
    }
    return [window makeFirstResponder:g_ime_text_view];
}

// Resign first responder from the global NSTextView.
void ime_clear_textview_first_responder(void) {
    if (!g_ime_text_view) {
        return;
    }
    [g_ime_text_view resignFirstResponder];
}

// ── Helper: get NSTextView from NSScrollView ──────────────────────────────
//
// Given an NSView that may be an NSScrollView, return the NSTextView inside it.
// The NSScrollView's documentView is an NSClipView, which contains the NSTextView.
// Returns the NSTextView directly if no scroll view wrapper is found.
void* ime_get_text_view(void* view) {
    NSView* nsView = (__bridge NSView*)view;
    // Try to get documentView (for NSScrollView)
    SEL sel = sel_registerName("documentView");
    IMP imp = [nsView methodForSelector:sel];
    if (imp) {
        id docView = ((id (*)(id, SEL))imp)(nsView, sel);
        if (docView) {
            // docView is an NSClipView — find the NSTextView subview
            NSArray* subviews = [docView subviews];
            for (id subview in subviews) {
                if ([subview isKindOfClass:[NSTextView class]]) {
                    return (__bridge void*)subview;
                }
            }
        }
    }
    // If not a scroll view, return the view itself
    return (__bridge void*)nsView;
}

// ── IME composition ───────────────────────────────────────────────────────

// Check if composing (marked text range non-empty).
int ime_is_composing(void) {
    if (!g_ime_text_view) {
        return 0;
    }
    SEL sel = sel_registerName("markedRange");
    IMP imp = [g_ime_text_view methodForSelector:sel];
    if (!imp) return 0;
    NSRange markedRange = ((NSRange (*)(id, SEL))imp)(g_ime_text_view, sel);
    return (markedRange.location != NSNotFound && markedRange.length > 0) ? 1 : 0;
}

// Unmark text (force end IME composition).
void ime_unmark_text(void) {
    if (!g_ime_text_view) {
        return;
    }
    SEL sel = sel_registerName("unmarkTextInRange:");
    IMP imp = [g_ime_text_view methodForSelector:sel];
    if (!imp) return;
    ((void (*)(id, SEL, NSRange))imp)(g_ime_text_view, sel, NSMakeRange(0, NSIntegerMax));
}

// Get composed (marked) text. Returns CFStringRef (caller must CFRelease) or NULL.
CFStringRef ime_get_composed_text(void) {
    if (!g_ime_text_view) {
        return NULL;
    }
    SEL sel = sel_registerName("markedText");
    IMP imp = [g_ime_text_view methodForSelector:sel];
    if (!imp) return NULL;
    NSAttributedString* markedText = ((NSAttributedString* (*)(id, SEL))imp)(g_ime_text_view, sel);
    if (!markedText || [markedText length] == 0) {
        return NULL;
    }
    return (__bridge CFStringRef)markedText.string;
}

// ── Core IME functions (reused from old bridge) ───────────────────────────

// Get the NSView for a control handle (uiControlHandle returns uintptr_t = NSView*)
void* ime_get_ns_view_from_control(void* control_handle) {
    NSView* view = (__bridge NSView*)control_handle;
    return (__bridge void*)view;
}

// Make a view the first responder
int ime_make_first_responder(void* view) {
    NSView* nsView = (__bridge NSView*)view;
    return [nsView becomeFirstResponder];
}

// Get the key window
void* ime_get_key_window(void) {
    return (__bridge void*)[NSApp keyWindow];
}

// Get the main window
void* ime_get_main_window(void) {
    return (__bridge void*)[NSApp mainWindow];
}

// Get the first responder of a window
void* ime_get_first_responder(void* window) {
    NSWindow* nsWindow = (__bridge NSWindow*)window;
    return (__bridge void*)[nsWindow firstResponder];
}

// Make a window key and order front
void ime_make_key_and_order_front(void* window) {
    NSWindow* nsWindow = (__bridge NSWindow*)window;
    [nsWindow makeKeyAndOrderFront:nil];
}

// Resign first responder from a view
void ime_resign_first_responder(void* view) {
    NSView* nsView = (__bridge NSView*)view;
    [nsView resignFirstResponder];
}

// Set a window's first responder to a specific view (overrides existing)
void ime_set_first_responder(void* window, void* view) {
    NSWindow* nsWindow = (__bridge NSWindow*)window;
    NSView* nsView = (__bridge NSView*)view;
    [nsWindow makeFirstResponder:nsView];
}

// Activate the app and make a view's window key, then make the view first responder.
// This is needed for the IME popup to appear - the IME framework requires the window
// to be properly key and the app to be active.
void ime_activate_and_focus_view(void* view) {
    NSView* nsView = (__bridge NSView*)view;
    [NSApp activateIgnoringOtherApps:YES];
    NSWindow* nsWindow = [nsView window];
    if (nsWindow) {
        [nsWindow makeKeyAndOrderFront:nil];
        [nsWindow makeFirstResponder:nsView];
    }
}

// Get the frame of an NSView in its superview's coordinate system.
void ime_get_view_frame(void* view, double* x, double* y, double* w, double* h) {
    NSView* nsView = (__bridge NSView*)view;
    NSRect frame = [nsView frame];
    if (x) *x = frame.origin.x;
    if (y) *y = frame.origin.y;
    if (w) *w = frame.size.width;
    if (h) *h = frame.size.height;
}

// Set the frame of an NSView
void ime_set_view_frame(void* view, double x, double y, double w, double h) {
    NSView* nsView = (__bridge NSView*)view;
    [nsView setFrame:NSMakeRect(x, y, w, h)];
}
