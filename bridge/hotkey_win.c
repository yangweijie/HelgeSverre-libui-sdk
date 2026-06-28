/**
 * hotkey_win.c — Global hotkey bridge for Windows
 *
 * Uses RegisterHotKey / UnregisterHotKey Win32 API to register system-wide
 * keyboard shortcuts. Requires a message queue (HWND_MESSAGE).
 *
 * Build:
 *   cl /LD hotkey_win.c /Fe:hotkey.dll user32.lib
 */

#define WIN32_LEAN_AND_MEAN
#include <windows.h>
#include <stdbool.h>
#include <stdio.h>

// ---------------------------------------------------------------------------
// State
// ---------------------------------------------------------------------------
static volatile int g_lastHotkeyId = 0;
static HWND g_hwnd = NULL;

#define MAX_HOTKEYS 32

static struct {
    int id;
    int modifiers;
    int vkey;
    bool active;
} g_hotkeys[MAX_HOTKEYS];

static int g_hotkeyCount = 0;

// ---------------------------------------------------------------------------
// Hidden window procedure — receives WM_HOTKEY messages
// ---------------------------------------------------------------------------
static LRESULT CALLBACK wnd_proc(HWND hwnd, UINT msg, WPARAM wp, LPARAM lp) {
    if (msg == WM_HOTKEY) {
        g_lastHotkeyId = (int)wp;
    }
    return DefWindowProc(hwnd, msg, wp, lp);
}

// ---------------------------------------------------------------------------
// Ensure hidden window exists
// ---------------------------------------------------------------------------
static HWND ensure_window(void) {
    if (g_hwnd) return g_hwnd;

    HINSTANCE hInst = GetModuleHandle(NULL);
    const char *CLASS_NAME = "PHP_GlobalHotkey_Window";

    WNDCLASS wc = {0};
    wc.lpfnWndProc = wnd_proc;
    wc.hInstance = hInst;
    wc.lpszClassName = CLASS_NAME;
    RegisterClass(&wc);

    g_hwnd = CreateWindowEx(0, CLASS_NAME, "PHP GlobalHotkey",
                            0, 0, 0, 0, 0,
                            HWND_MESSAGE, NULL, hInst, NULL);
    return g_hwnd;
}

// ---------------------------------------------------------------------------
// Map modifier string tokens to Win32 MOD_ constants
// ---------------------------------------------------------------------------
static int parse_modifiers(const char *mods, const char **end) {
    int mask = 0;
    const char *p = mods;

    while (*p) {
        while (*p == '+') p++;

        if (strncmp(p, "Cmd", 3) == 0 || strncmp(p, "Command", 7) == 0) {
            mask |= MOD_WIN;
            p = strchr(p, '+') ? strchr(p, '+') + 1 : p + 3;
        } else if (strncmp(p, "Shift", 5) == 0) {
            mask |= MOD_SHIFT;
            p = strchr(p, '+') ? strchr(p, '+') + 1 : p + 5;
        } else if (strncmp(p, "Alt", 3) == 0 || strncmp(p, "Option", 6) == 0) {
            mask |= MOD_ALT;
            p = strchr(p, '+') ? strchr(p, '+') + 1 : p + 3;
        } else if (strncmp(p, "Ctrl", 4) == 0 || strncmp(p, "Control", 7) == 0) {
            mask |= MOD_CONTROL;
            p = strchr(p, '+') ? strchr(p, '+') + 1 : p + 4;
        } else {
            break;
        }
    }

    if (end) *end = p;
    return mask;
}

// ---------------------------------------------------------------------------
// Map key name string to Windows virtual key code
// ---------------------------------------------------------------------------
static int parse_key_code(const char *key) {
    while (*key == '+') key++;

    // Letters (A-Z)
    if (strlen(key) == 1 && key[0] >= 'A' && key[0] <= 'Z')
        return 'A' + (key[0] - 'A');
    if (strlen(key) == 1 && key[0] >= 'a' && key[0] <= 'z')
        return 'A' + (key[0] - 'a');

    // Digits
    if (strlen(key) == 1 && key[0] >= '0' && key[0] <= '9')
        return '0' + (key[0] - '0');

    // Function keys
    if (strlen(key) == 2 && key[0] == 'F' && key[1] >= '1' && key[1] <= '9')
        return VK_F1 + (key[1] - '1');
    if (strcmp(key, "F10") == 0) return VK_F10;
    if (strcmp(key, "F11") == 0) return VK_F11;
    if (strcmp(key, "F12") == 0) return VK_F12;

    // Named keys (case-insensitive)
    char lower[16]; size_t i;
    for (i = 0; i < strlen(key) && i < 15; i++)
        lower[i] = (key[i] >= 'A' && key[i] <= 'Z') ? (key[i] + 32) : key[i];
    lower[i] = '\0';

    if (strcmp(lower, "space") == 0) return VK_SPACE;
    if (strcmp(lower, "return") == 0 || strcmp(lower, "enter") == 0) return VK_RETURN;
    if (strcmp(lower, "escape") == 0 || strcmp(lower, "esc") == 0) return VK_ESCAPE;
    if (strcmp(lower, "tab") == 0) return VK_TAB;
    if (strcmp(lower, "delete") == 0 || strcmp(lower, "backspace") == 0) return VK_BACK;
    if (strcmp(lower, "up") == 0) return VK_UP;
    if (strcmp(lower, "down") == 0) return VK_DOWN;
    if (strcmp(lower, "left") == 0) return VK_LEFT;
    if (strcmp(lower, "right") == 0) return VK_RIGHT;
    if (strcmp(lower, "home") == 0) return VK_HOME;
    if (strcmp(lower, "end") == 0) return VK_END;
    if (strcmp(lower, "pageup") == 0) return VK_PRIOR;
    if (strcmp(lower, "pagedown") == 0) return VK_NEXT;

    return 0; // Unknown
}

// ---------------------------------------------------------------------------
// Public C API
// ---------------------------------------------------------------------------

int hotkey_register(const char *key_combo, int id) {
    if (g_hotkeyCount >= MAX_HOTKEYS) return 0;

    HWND hwnd = ensure_window();
    if (!hwnd) return 0;

    const char *keyStr;
    int modifiers = parse_modifiers(key_combo, &keyStr);
    int vkey = parse_key_code(keyStr);

    if (vkey == 0) {
        fprintf(stderr, "[hotkey] Unknown key: %s\n", keyStr);
        return 0;
    }

    if (!RegisterHotKey(hwnd, id, modifiers, vkey)) {
        fprintf(stderr, "[hotkey] RegisterHotKey failed for: %s\n", key_combo);
        return 0;
    }

    g_hotkeys[g_hotkeyCount].id = id;
    g_hotkeys[g_hotkeyCount].modifiers = modifiers;
    g_hotkeys[g_hotkeyCount].vkey = vkey;
    g_hotkeys[g_hotkeyCount].active = true;
    g_hotkeyCount++;

    return 1;
}

void hotkey_unregister(int id) {
    if (!g_hwnd) return;

    for (int i = 0; i < g_hotkeyCount; i++) {
        if (g_hotkeys[i].id == id && g_hotkeys[i].active) {
            UnregisterHotKey(g_hwnd, id);
            g_hotkeys[i].active = false;
            break;
        }
    }
}

void hotkey_unregister_all(void) {
    if (!g_hwnd) return;

    for (int i = 0; i < g_hotkeyCount; i++) {
        if (g_hotkeys[i].active) {
            UnregisterHotKey(g_hwnd, g_hotkeys[i].id);
            g_hotkeys[i].active = false;
        }
    }
    g_hotkeyCount = 0;
}

int hotkey_poll(void) {
    if (!g_hwnd) return 0;

    // Process messages to receive WM_HOTKEY
    MSG msg;
    while (PeekMessage(&msg, g_hwnd, WM_HOTKEY, WM_HOTKEY, PM_REMOVE)) {
        TranslateMessage(&msg);
        DispatchMessage(&msg);
    }

    int id = g_lastHotkeyId;
    g_lastHotkeyId = 0;
    return id;
}