/**
 * hotkey.m — Global hotkey bridge for macOS
 *
 * Uses Carbon RegisterEventHotKey / UnregisterEventHotKey to register
 * system-wide keyboard shortcuts that work even when the app is in the
 * background.
 *
 * Communication with PHP is via a simple polling mechanism:
 *   - When a hotkey is pressed, the Carbon event handler stores the hotkey
 *     ID in a shared atomic variable.
 *   - PHP calls hotkey_poll() periodically (via a libui timer) to check
 *     if a hotkey was pressed.
 *
 * Build:
 *   clang -shared -fobjc-arc hotkey.m \
 *         -framework Carbon -framework AppKit \
 *         -o hotkey.dylib
 */

#import <Cocoa/Cocoa.h>
#import <Carbon/Carbon.h>

// ---------------------------------------------------------------------------
// Atomic state: last pressed hotkey ID (0 = none)
// ---------------------------------------------------------------------------
static volatile int32_t g_lastHotkeyId = 0;

// ---------------------------------------------------------------------------
// Max supported hotkeys
// ---------------------------------------------------------------------------
#define MAX_HOTKEYS 32

static struct {
    int id;
    EventHotKeyRef ref;
    bool active;
} g_hotkeys[MAX_HOTKEYS];

static int g_hotkeyCount = 0;

// ---------------------------------------------------------------------------
// OSStatus helper macro
// ---------------------------------------------------------------------------
#define CHECK(status, msg) do { \
    if ((status) != noErr) { \
        fprintf(stderr, "[hotkey] %s: %d\n", msg, (int)(status)); \
        return 0; \
    } \
} while(0)

// ---------------------------------------------------------------------------
// Map modifier string tokens to Carbon modifier masks
// ---------------------------------------------------------------------------
static uint32_t parse_modifiers(const char *mods, const char **end) {
    uint32_t mask = 0;
    const char *p = mods;

    while (*p) {
        // Skip '+' separators
        while (*p == '+') p++;

        if (strncmp(p, "Cmd", 3) == 0 || strncmp(p, "Command", 7) == 0) {
            mask |= cmdKey;
            p = strchr(p, '+') ? strchr(p, '+') + 1 : p + 3;
        } else if (strncmp(p, "Shift", 5) == 0) {
            mask |= shiftKey;
            p = strchr(p, '+') ? strchr(p, '+') + 1 : p + 5;
        } else if (strncmp(p, "Alt", 3) == 0 || strncmp(p, "Option", 6) == 0) {
            mask |= optionKey;
            p = strchr(p, '+') ? strchr(p, '+') + 1 : p + 3;
        } else if (strncmp(p, "Ctrl", 4) == 0 || strncmp(p, "Control", 7) == 0) {
            mask |= controlKey;
            p = strchr(p, '+') ? strchr(p, '+') + 1 : p + 4;
        } else {
            break;
        }
    }

    if (end) *end = p;
    return mask;
}

// ---------------------------------------------------------------------------
// Map key name string to Carbon virtual key code
// ---------------------------------------------------------------------------
static uint16_t parse_key_code(const char *key) {
    // Skip leading '+'
    while (*key == '+') key++;

    // Empty or too short
    if (!key[0]) return 0;

    char c = key[0];
    size_t len = strlen(key);

    // Function keys (F1-F9)
    if (len == 2 && c == 'F') {
        char n = key[1];
        if (n >= '1' && n <= '9') {
            static const uint16_t fmap[] = {kVK_F1, kVK_F2, kVK_F3, kVK_F4, kVK_F5, kVK_F6, kVK_F7, kVK_F8, kVK_F9};
            return fmap[n - '1'];
        }
        return 0xFFFF; // Unknown
    }
    if (len == 3 && key[0] == 'F' && key[1] == '1') {
        char n = key[2];
        if (n == '0') return kVK_F10;
        if (n == '1') return kVK_F11;
        if (n == '2') return kVK_F12;
        return 0xFFFF;
    }

    // Letters (A-Z, case insensitive)
    if (len == 1 && ((c >= 'A' && c <= 'Z') || (c >= 'a' && c <= 'z'))) {
        char upper = (c >= 'a') ? (c - 32) : c;
        static const uint16_t letterMap[] = {
            kVK_ANSI_A, kVK_ANSI_B, kVK_ANSI_C, kVK_ANSI_D, kVK_ANSI_E,
            kVK_ANSI_F, kVK_ANSI_G, kVK_ANSI_H, kVK_ANSI_I, kVK_ANSI_J,
            kVK_ANSI_K, kVK_ANSI_L, kVK_ANSI_M, kVK_ANSI_N, kVK_ANSI_O,
            kVK_ANSI_P, kVK_ANSI_Q, kVK_ANSI_R, kVK_ANSI_S, kVK_ANSI_T,
            kVK_ANSI_U, kVK_ANSI_V, kVK_ANSI_W, kVK_ANSI_X, kVK_ANSI_Y,
            kVK_ANSI_Z
        };
        return letterMap[upper - 'A'];
    }

    // Numbers
    if (len == 1 && c >= '0' && c <= '9') {
        static const uint16_t digitMap[] = {
            kVK_ANSI_0, kVK_ANSI_1, kVK_ANSI_2, kVK_ANSI_3, kVK_ANSI_4,
            kVK_ANSI_5, kVK_ANSI_6, kVK_ANSI_7, kVK_ANSI_8, kVK_ANSI_9
        };
        return digitMap[c - '0'];
    }

    // Named keys (case insensitive)
    char lower[16];
    size_t i;
    for (i = 0; i < len && i < 15; i++) {
        char ch = key[i];
        lower[i] = (ch >= 'A' && ch <= 'Z') ? (ch + 32) : ch;
    }
    lower[i] = '\0';

    if (strcmp(lower, "space") == 0) return kVK_Space;
    if (strcmp(lower, "return") == 0 || strcmp(lower, "enter") == 0) return kVK_Return;
    if (strcmp(lower, "escape") == 0 || strcmp(lower, "esc") == 0) return kVK_Escape;
    if (strcmp(lower, "tab") == 0) return kVK_Tab;
    if (strcmp(lower, "delete") == 0 || strcmp(lower, "backspace") == 0) return kVK_Delete;
    if (strcmp(lower, "up") == 0) return kVK_UpArrow;
    if (strcmp(lower, "down") == 0) return kVK_DownArrow;
    if (strcmp(lower, "left") == 0) return kVK_LeftArrow;
    if (strcmp(lower, "right") == 0) return kVK_RightArrow;
    if (strcmp(lower, "home") == 0) return kVK_Home;
    if (strcmp(lower, "end") == 0) return kVK_End;
    if (strcmp(lower, "pageup") == 0) return kVK_PageUp;
    if (strcmp(lower, "pagedown") == 0) return kVK_PageDown;

    return 0xFFFF; // Unknown sentinel (kVK_ANSI_A is 0, so 0xFFFF is safe)
}

// ---------------------------------------------------------------------------
// Carbon event handler — called when a registered hotkey is pressed
// ---------------------------------------------------------------------------
static OSStatus hotkey_handler(EventHandlerCallRef next, EventRef event, void *ctx) {
    (void)next;
    (void)ctx;

    EventHotKeyID hkID;
    OSStatus status = GetEventParameter(event, kEventParamDirectObject,
                                        typeEventHotKeyID, NULL,
                                        sizeof(EventHotKeyID), NULL, &hkID);
    if (status == noErr) {
        g_lastHotkeyId = hkID.id;
    }

    return noErr;
}

// ---------------------------------------------------------------------------
// Public C API
// ---------------------------------------------------------------------------

/**
 * Register a global hotkey.
 *
 * @param key_combo  Key combination string, e.g. "Cmd+Shift+A", "Ctrl+Alt+F1"
 * @param id         Unique integer ID for this hotkey (returned by hotkey_poll)
 * @return 1 on success, 0 on failure
 */
int hotkey_register(const char *key_combo, int id) {
    if (g_hotkeyCount >= MAX_HOTKEYS) {
        fprintf(stderr, "[hotkey] Max hotkeys reached (%d)\n", MAX_HOTKEYS);
        return 0;
    }

    // Parse the key combination
    const char *keyStr;
    uint32_t modifiers = parse_modifiers(key_combo, &keyStr);
    uint16_t keyCode = parse_key_code(keyStr);

    if (keyCode == 0xFFFF) {
        fprintf(stderr, "[hotkey] Unknown key: %s\n", keyStr);
        return 0;
    }

    // Register with Carbon
    EventHotKeyID hkID = { .signature = 'PHPH', .id = id };

    EventHotKeyRef ref = NULL;
    OSStatus status = RegisterEventHotKey(keyCode, modifiers, hkID,
                                          GetApplicationEventTarget(),
                                          0, &ref);
    if (status != noErr) {
        fprintf(stderr, "[hotkey] RegisterEventHotKey failed: %d\n", (int)status);
        return 0;
    }

    // Install event handler the first time
    static bool handlerInstalled = false;
    if (!handlerInstalled) {
        EventTypeSpec eventType = { .eventClass = kEventClassKeyboard,
                                    .eventKind = kEventHotKeyPressed };
        InstallApplicationEventHandler(NewEventHandlerUPP(hotkey_handler),
                                       1, &eventType, NULL, NULL);
        handlerInstalled = true;
    }

    // Store the reference
    g_hotkeys[g_hotkeyCount].id = id;
    g_hotkeys[g_hotkeyCount].ref = ref;
    g_hotkeys[g_hotkeyCount].active = true;
    g_hotkeyCount++;

    return 1;
}

/**
 * Unregister a previously registered hotkey by ID.
 */
void hotkey_unregister(int id) {
    for (int i = 0; i < g_hotkeyCount; i++) {
        if (g_hotkeys[i].id == id && g_hotkeys[i].active) {
            UnregisterEventHotKey(g_hotkeys[i].ref);
            g_hotkeys[i].active = false;
            break;
        }
    }
}

/**
 * Unregister all hotkeys.
 */
void hotkey_unregister_all(void) {
    for (int i = 0; i < g_hotkeyCount; i++) {
        if (g_hotkeys[i].active) {
            UnregisterEventHotKey(g_hotkeys[i].ref);
            g_hotkeys[i].active = false;
        }
    }
    g_hotkeyCount = 0;
}

/**
 * Poll: return the ID of the last pressed hotkey, then reset to 0.
 */
int hotkey_poll(void) {
    int id = g_lastHotkeyId;
    g_lastHotkeyId = 0;
    return id;
}