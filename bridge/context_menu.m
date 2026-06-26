/**
 * context_menu.m — Native popup context menu bridge (macOS)
 *
 * Uses NSMenu's popUpMenuPositioningItem:atLocation:inView: — the
 * standard AppKit way to show a native context menu. Renders with
 * correct system theme (light/dark), proper animation, and zero
 * custom rendering (no black backgrounds, no busy cursor).
 *
 * Build:
 *   cd bridge && clang -shared -fobjc-arc context_menu.m \
 *         -framework Foundation -framework AppKit \
 *         -o context_menu.dylib
 */

#import <Cocoa/Cocoa.h>

// ---------------------------------------------------------------------------
// Helper object: acts as target for every NSMenuItem and stores the selected
// tag. Used to communicate the selection back to the C caller after the
// blocking popUpMenuPositioningItem: call returns.
// ---------------------------------------------------------------------------
@interface MenuHelper : NSObject
@property (atomic) int selectedTag;
@property (atomic) BOOL didSelect;
- (void)itemAction:(id)sender;
@end

@implementation MenuHelper
- (void)itemAction:(id)sender {
    self.selectedTag = (int)[sender tag];
    self.didSelect = YES;
}
@end

// ---------------------------------------------------------------------------
// Public C API — called from PHP via FFI
// ---------------------------------------------------------------------------
int cm_show_menu(const char *menu_json, double x, double y) {
    @autoreleasepool {
        NSString *jsonStr = [NSString stringWithUTF8String:menu_json ?: "[]"];
        NSArray *items = [NSJSONSerialization JSONObjectWithData:[jsonStr dataUsingEncoding:NSUTF8StringEncoding]
                                                         options:0
                                                           error:nil];
        if (![items isKindOfClass:[NSArray class]]) return -1;

        // --- Build the NSMenu ---
        NSMenu *menu = [[NSMenu alloc] initWithTitle:@"ContextMenu"];

        for (int i = 0; i < (int)items.count; i++) {
            NSDictionary *d = items[i];
            NSString *text = d[@"text"] ?: @"";

            if ([text isEqualToString:@"-"]) {
                [menu addItem:[NSMenuItem separatorItem]];
                continue;
            }

            NSMenuItem *mi = [[NSMenuItem alloc] initWithTitle:text
                                                        action:NULL
                                                 keyEquivalent:@""];
            mi.tag = i;
            mi.enabled = ![d[@"disabled"] boolValue];
            mi.state = [d[@"checked"] boolValue] ? NSControlStateValueOn : NSControlStateValueOff;

            [menu addItem:mi];
        }

        // --- Create helper and wire up action targets ---
        MenuHelper *helper = [[MenuHelper alloc] init];
        helper.selectedTag = -1;
        helper.didSelect = NO;

        for (NSMenuItem *mi in [menu itemArray]) {
            if (!mi.isSeparatorItem && mi.isEnabled) {
                mi.target = helper;
                mi.action = @selector(itemAction:);
            }
        }

        // --- Create a temporary invisible window to host the menu ---
        // NSMenu needs an NSView context; we provide a tiny offscreen window.
        NSPoint cursor = [NSEvent mouseLocation];
        NSRect offRect = NSMakeRect(cursor.x - 50, cursor.y - 50, 1, 1);
        NSWindow *tmpWin = [[NSWindow alloc] initWithContentRect:offRect
                                                       styleMask:NSWindowStyleMaskBorderless
                                                         backing:NSBackingStoreBuffered
                                                           defer:NO];
        [tmpWin setOpaque:NO];
        [tmpWin setBackgroundColor:[NSColor clearColor]];
        [tmpWin setIgnoresMouseEvents:YES];
        [tmpWin setLevel:NSStatusWindowLevel];
        [tmpWin orderFront:nil];

        NSView *hostView = [[NSView alloc] initWithFrame:NSMakeRect(0, 0, 1, 1)];
        [tmpWin setContentView:hostView];

        // Popup position: at the cursor, in the host view's coordinate system.
        // The host view is at (0,0) in the window, and the window is at
        // (cursor.x - 50, cursor.y - 50), so the cursor in view coords is:
        NSPoint menuOrigin = NSMakePoint(50, 50);

        // --- Show the native context menu (synchronous, blocks until dismissed) ---
        BOOL didSelect = [menu popUpMenuPositioningItem:nil
                                             atLocation:menuOrigin
                                                 inView:hostView];

        // Clean up the temporary window
        [tmpWin orderOut:nil];

        if (didSelect && helper.didSelect) {
            return helper.selectedTag;
        }

        return -1;
    }
}