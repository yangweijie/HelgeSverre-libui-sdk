#include <Foundation/Foundation.h>
#include <AppKit/AppKit.h>

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

// Set the frame of an NSView (used to position IME popup near the TextArea)
void ime_set_view_frame(void* view, double x, double y, double w, double h) {
    NSView* nsView = (__bridge NSView*)view;
    [nsView setFrame:NSMakeRect(x, y, w, h)];
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
    // Activate the app to bring it to the foreground
    [NSApp activateIgnoringOtherApps:YES];
    // Get the window containing this view
    NSWindow* nsWindow = [nsView window];
    if (nsWindow) {
        // Make the window key and ordered front
        [nsWindow makeKeyAndOrderFront:nil];
        // Make the view first responder
        [nsWindow makeFirstResponder:nsView];
    }
}

// Get the frame of an NSView in its superview's coordinate system.
// Used to compute the offset between the Surface Area and the parent Box,
// so IME popup coordinates can be converted from Surface-local to parent-relative.
void ime_get_view_frame(void* view, double* x, double* y, double* w, double* h) {
    NSView* nsView = (__bridge NSView*)view;
    NSRect frame = [nsView frame];
    if (x) *x = frame.origin.x;
    if (y) *y = frame.origin.y;
    if (w) *w = frame.size.width;
    if (h) *h = frame.size.height;
}

// Get the documentView (NSTextView) from a view that may be an NSScrollView.
// libui's MultilineEntry wraps an NSScrollView, so we need to dig out the NSTextView.
void* ime_get_text_view(void* view) {
    NSView* nsView = (__bridge NSView*)view;
    // Try to get documentView (for NSScrollView)
    SEL sel = sel_registerName("documentView");
    IMP imp = [nsView methodForSelector:sel];
    if (imp) {
        id docView = ((id (*)(id, SEL))imp)(nsView, sel);
        if (docView) {
            return (__bridge void*)docView;
        }
    }
    // If not a scroll view, return the view itself
    return (__bridge void*)nsView;
}

// Check if an NSTextView is currently in IME composition mode.
// Returns 1 if marked text range is non-empty (composing), 0 otherwise.
// Composition means the user is typing pinyin/hiragana etc. and the IME popup is visible.
// Arrow keys during composition navigate the IME candidate list — this is correct behavior.
// After composition ends (candidate selected), arrow keys should go to the app (Surface Area).
int ime_is_composing(void* view) {
    // First get the actual text view (handle NSScrollView wrapper)
    SEL sel_doc = sel_registerName("documentView");
    IMP imp_doc = [(id)view methodForSelector:sel_doc];
    id textView = view;
    if (imp_doc) {
        id docView = ((id (*)(id, SEL))imp_doc)(view, sel_doc);
        if (docView) {
            textView = docView;
        }
    }

    SEL sel = sel_registerName("markedRange");
    IMP imp = [(id)textView methodForSelector:sel];
    if (!imp) return 0;
    NSRange markedRange = ((NSRange (*)(id, SEL))imp)(textView, sel);
    return (markedRange.location != NSNotFound && markedRange.length > 0);
}

// Unmark text (force end IME composition).
// Use with caution: only call when you want to discard the current composition.
void ime_unmark_text(void* view) {
    // First get the actual text view (handle NSScrollView wrapper)
    SEL sel_doc = sel_registerName("documentView");
    IMP imp_doc = [(id)view methodForSelector:sel_doc];
    id textView = view;
    if (imp_doc) {
        id docView = ((id (*)(id, SEL))imp_doc)(view, sel_doc);
        if (docView) {
            textView = docView;
        }
    }

    SEL sel = sel_registerName("unmarkTextInRange:");
    IMP imp = [(id)textView methodForSelector:sel];
    if (imp) {
        ((void (*)(id, SEL, NSRange))imp)(textView, sel, NSMakeRange(0, NSIntegerMax));
    }
}
