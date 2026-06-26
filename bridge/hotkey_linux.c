/**
 * hotkey_linux.c — Global hotkey bridge for Linux (X11)
 *
 * Uses XGrabKey / XUngrabKey via X11 to register system-wide keyboard
 * shortcuts. Requires an X11 display connection and a visible window.
 *
 * Build:
 *   gcc -shared -fPIC hotkey_linux.c \
 *       $(pkg-config --cflags --libs x11) \
 *       -o libhotkey.so
 */

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <X11/Xlib.h>
#include <X11/Xutil.h>
#include <X11/keysym.h>

// ---------------------------------------------------------------------------
// State
// ---------------------------------------------------------------------------
static volatile int g_lastHotkeyId = 0;
static Display *g_display = NULL;
static Window g_root = 0;

#define MAX_HOTKEYS 32

static struct {
    int id;
    int keycode;
    unsigned int modifiers;
    bool active;
} g_hotkeys[MAX_HOTKEYS];

static int g_hotkeyCount = 0;

// ---------------------------------------------------------------------------
// Map modifier string tokens to X11 modifier masks
// ---------------------------------------------------------------------------
static unsigned int parse_modifiers(const char *mods, const char **end) {
    unsigned int mask = 0;
    const char *p = mods;

    while (*p) {
        while (*p == '+') p++;

        if (strncmp(p, "Cmd", 3) == 0 || strncmp(p, "Command", 7) == 0) {
            mask |= Mod4Mask; // Super/Command key on most Linux
            p = strchr(p, '+') ? strchr(p, '+') + 1 : p + 3;
        } else if (strncmp(p, "Shift", 5) == 0) {
            mask |= ShiftMask;
            p = strchr(p, '+') ? strchr(p, '+') + 1 : p + 5;
        } else if (strncmp(p, "Alt", 3) == 0 || strncmp(p, "Option", 6) == 0) {
            mask |= Mod1Mask;
            p = strchr(p, '+') ? strchr(p, '+') + 1 : p + 3;
        } else if (strncmp(p, "Ctrl", 4) == 0 || strncmp(p, "Control", 7) == 0) {
            mask |= ControlMask;
            p = strchr(p, '+') ? strchr(p, '+') + 1 : p + 4;
        } else {
            break;
        }
    }

    if (end) *end = p;
    return mask;
}

// ---------------------------------------------------------------------------
// Map key name string to X11 KeySym then to keycode
// ---------------------------------------------------------------------------
static KeySym key_name_to_keysym(const char *key) {
    while (*key == '+') key++;

    // Letters
    if (strlen(key) == 1 && key[0] >= 'A' && key[0] <= 'Z')
        return XK_A + (key[0] - 'A');
    if (strlen(key) == 1 && key[0] >= 'a' && key[0] <= 'z')
        return XK_a + (key[0] - 'a');

    // Digits
    if (strlen(key) == 1 && key[0] >= '0' && key[0] <= '9')
        return XK_0 + (key[0] - '0');

    // Function keys
    if (strlen(key) == 2 && key[0] == 'F' && key[1] >= '1' && key[1] <= '9')
        return XK_F1 + (key[1] - '1');
    if (strcmp(key, "F10") == 0) return XK_F10;
    if (strcmp(key, "F11") == 0) return XK_F11;
    if (strcmp(key, "F12") == 0) return XK_F12;

    // Named keys (case-insensitive)
    char lower[16]; size_t i;
    for (i = 0; i < strlen(key) && i < 15; i++)
        lower[i] = (key[i] >= 'A' && key[i] <= 'Z') ? (key[i] + 32) : key[i];
    lower[i] = '\0';

    if (strcmp(lower, "space") == 0) return XK_space;
    if (strcmp(lower, "return") == 0 || strcmp(lower, "enter") == 0) return XK_Return;
    if (strcmp(lower, "escape") == 0 || strcmp(lower, "esc") == 0) return XK_Escape;
    if (strcmp(lower, "tab") == 0) return XK_Tab;
    if (strcmp(lower, "delete") == 0 || strcmp(lower, "backspace") == 0) return XK_BackSpace;
    if (strcmp(lower, "up") == 0) return XK_Up;
    if (strcmp(lower, "down") == 0) return XK_Down;
    if (strcmp(lower, "left") == 0) return XK_Left;
    if (strcmp(lower, "right") == 0) return XK_Right;
    if (strcmp(lower, "home") == 0) return XK_Home;
    if (strcmp(lower, "end") == 0) return XK_End;
    if (strcmp(lower, "pageup") == 0) return XK_Page_Up;
    if (strcmp(lower, "pagedown") == 0) return XK_Page_Down;

    return NoSymbol;
}

// ---------------------------------------------------------------------------
// Public C API
// ---------------------------------------------------------------------------

int hotkey_register(const char *key_combo, int id) {
    if (g_hotkeyCount >= MAX_HOTKEYS) return 0;

    // Connect to X11 on first call
    if (!g_display) {
        g_display = XOpenDisplay(NULL);
        if (!g_display) {
            fprintf(stderr, "[hotkey] Cannot open X display\n");
            return 0;
        }
        g_root = RootWindow(g_display, DefaultScreen(g_display));
    }

    const char *keyStr;
    unsigned int modifiers = parse_modifiers(key_combo, &keyStr);
    KeySym sym = key_name_to_keysym(keyStr);

    if (sym == NoSymbol) {
        fprintf(stderr, "[hotkey] Unknown key: %s\n", keyStr);
        return 0;
    }

    int keycode = XKeysymToKeycode(g_display, sym);
    if (keycode == 0) {
        fprintf(stderr, "[hotkey] Cannot map key to keycode: %s\n", keyStr);
        return 0;
    }

    // Grab the key on the root window
    XGrabKey(g_display, keycode, modifiers, g_root, False,
             GrabModeAsync, GrabModeAsync);
    XGrabKey(g_display, keycode, modifiers | Mod2Mask, g_root, False,
             GrabModeAsync, GrabModeAsync); // NumLock variant
    XGrabKey(g_display, keycode, modifiers | LockMask, g_root, False,
             GrabModeAsync, GrabModeAsync); // CapsLock variant

    g_hotkeys[g_hotkeyCount].id = id;
    g_hotkeys[g_hotkeyCount].keycode = keycode;
    g_hotkeys[g_hotkeyCount].modifiers = modifiers;
    g_hotkeys[g_hotkeyCount].active = true;
    g_hotkeyCount++;

    return 1;
}

void hotkey_unregister(int id) {
    if (!g_display) return;

    for (int i = 0; i < g_hotkeyCount; i++) {
        if (g_hotkeys[i].id == id && g_hotkeys[i].active) {
            XUngrabKey(g_display, g_hotkeys[i].keycode,
                       g_hotkeys[i].modifiers, g_root);
            XUngrabKey(g_display, g_hotkeys[i].keycode,
                       g_hotkeys[i].modifiers | Mod2Mask, g_root);
            XUngrabKey(g_display, g_hotkeys[i].keycode,
                       g_hotkeys[i].modifiers | LockMask, g_root);
            g_hotkeys[i].active = false;
            break;
        }
    }
}

void hotkey_unregister_all(void) {
    if (!g_display) return;

    for (int i = 0; i < g_hotkeyCount; i++) {
        if (g_hotkeys[i].active) {
            XUngrabKey(g_display, g_hotkeys[i].keycode,
                       g_hotkeys[i].modifiers, g_root);
            XUngrabKey(g_display, g_hotkeys[i].keycode,
                       g_hotkeys[i].modifiers | Mod2Mask, g_root);
            XUngrabKey(g_display, g_hotkeys[i].keycode,
                       g_hotkeys[i].modifiers | LockMask, g_root);
            g_hotkeys[i].active = false;
        }
    }
    g_hotkeyCount = 0;
}

int hotkey_poll(void) {
    if (!g_display) return 0;

    // Process X events to detect hotkey presses
    // XGrabKey sends KeyPress events to the grabbing client
    XEvent ev;
    while (XPending(g_display)) {
        XNextEvent(g_display, &ev);
        if (ev.type == KeyPress) {
            // Find which registered hotkey matches
            XKeyEvent *ke = &ev.xkey;
            for (int i = 0; i < g_hotkeyCount; i++) {
                if (g_hotkeys[i].active &&
                    g_hotkeys[i].keycode == ke->keycode &&
                    (g_hotkeys[i].modifiers & ~(LockMask | Mod2Mask)) ==
                    (ke->state & ~(LockMask | Mod2Mask))) {
                    g_lastHotkeyId = g_hotkeys[i].id;
                }
            }
        }
    }

    int id = g_lastHotkeyId;
    g_lastHotkeyId = 0;
    return id;
}