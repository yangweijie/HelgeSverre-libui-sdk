/**
 * ime_bridge_win.c — Native IME text overlay bridge (Windows / Win32)
 *
 * Mirrors the macOS ime_bridge.m API surface so the same PHP FFI cdef works
 * on all three platforms. Instead of an NSTextView it uses a native Win32
 * EDIT control (Unicode) hosted as a child of the libui Area HWND. Win32
 * EDIT controls handle IME composition natively — the preedit/composition
 * string is drawn by the control itself, exactly like the macOS overlay.
 *
 * Single-line fields (vcenter != 0) get a single-line EDIT; multi-line
 * fields (vcenter == 0, e.g. TextArea) get ES_MULTILINE.
 *
 * Text is exchanged as UTF-8 with PHP (the EDIT control uses UTF-16
 * internally), so all strings are converted on the boundary.
 *
 * Build (MSVC):
 *   cl /LD bridge/ime_bridge_win.c /Fe:bridge/ime_bridge.dll user32.lib gdi32.lib
 *
 * Build (MinGW):
 *   gcc -shared bridge/ime_bridge_win.c -o bridge/ime_bridge.dll -luser32 -lgdi32
 */

#define WIN32_LEAN_AND_MEAN
#include <windows.h>
#include <stdint.h>
#include <string.h>
#include <stdlib.h>
#include <stdio.h>

/* Tracks IME composition state (marked text). */
static BOOL g_composing = FALSE;

/* The live EDIT control and its direct parent (the libui Area HWND). */
static HWND g_hwnd = NULL;
static HWND g_parent = NULL;

/* Original window procedures (for safe subclass chaining on destroy). */
static WNDPROC g_old_edit_proc = NULL;
static WNDPROC g_old_parent_proc = NULL;

/* Font handle kept alive for the EDIT control's lifetime. */
static HFONT g_font = NULL;

/* Control id assigned to the EDIT so we can recognise EN_CHANGE in the
 * parent's WM_COMMAND stream. */
static const UINT g_ctrl_id = 4242;

/* PHP-provided callbacks. */
typedef void (*ime_notify_callback_t)(const char*, int);
typedef void (*ime_tab_callback_t)(int);
static ime_notify_callback_t g_notify_callback = NULL;
static ime_tab_callback_t g_tab_callback = NULL;

/* Forward declaration — ime_create_textview may tear down a previous overlay. */
void ime_destroy_textview(void);

/* ── Helpers ─────────────────────────────────────────────────────────────── */

/* Convert UTF-8 → UTF-16 (caller frees with free()). */
static wchar_t* utf8_to_wcs(const char* s) {
    if (!s) return NULL;
    int wlen = MultiByteToWideChar(CP_UTF8, 0, s, -1, NULL, 0);
    if (wlen <= 0) return NULL;
    wchar_t* w = (wchar_t*)malloc((size_t)wlen * sizeof(wchar_t));
    MultiByteToWideChar(CP_UTF8, 0, s, -1, w, wlen);
    return w;
}

/* Convert UTF-16 → UTF-8 (caller frees with free()). */
static char* wcs_to_utf8(const wchar_t* w) {
    if (!w) return NULL;
    int n = WideCharToMultiByte(CP_UTF8, 0, w, -1, NULL, 0, NULL, NULL);
    if (n <= 0) return NULL;
    char* s = (char*)malloc((size_t)n);
    WideCharToMultiByte(CP_UTF8, 0, w, -1, s, n, NULL, NULL);
    return s;
}

/* Read current text + caret and fire the notify callback. */
static void fire_notify(void) {
    if (!g_notify_callback || !g_hwnd) return;

    int len = GetWindowTextLengthW(g_hwnd);
    wchar_t* wbuf = (wchar_t*)malloc((size_t)(len + 1) * sizeof(wchar_t));
    GetWindowTextW(g_hwnd, wbuf, len + 1);

    char* ubuf = wcs_to_utf8(wbuf);

    DWORD start = 0, end = 0;
    SendMessageW(g_hwnd, EM_GETSEL, (WPARAM)&start, (LPARAM)&end);
    int caret = (int)start;

    g_notify_callback(ubuf ? ubuf : "", caret);

    free(ubuf);
    free(wbuf);
}

/* ── Subclass procedures ─────────────────────────────────────────────────── */

/* Subclass of the EDIT control: swallow Tab/Shift+Tab (→ focus nav callback)
 * and track IME composition start/end. Everything else passes through. */
static LRESULT CALLBACK edit_subclass(HWND hwnd, UINT msg, WPARAM wp, LPARAM lp) {
    if (msg == WM_KEYDOWN && wp == VK_TAB) {
        if (g_tab_callback) {
            g_tab_callback((GetKeyState(VK_SHIFT) & 0x8000) ? 1 : 0);
        }
        return 0; /* don't insert a tab character */
    }
    if (msg == WM_IME_STARTCOMPOSITION) {
        g_composing = TRUE;
    } else if (msg == WM_IME_ENDCOMPOSITION) {
        g_composing = FALSE;
    }
    return CallWindowProc(g_old_edit_proc, hwnd, msg, wp, lp);
}

/* Subclass of the parent (Area HWND): intercept EN_CHANGE for our EDIT and
 * forward it to the notify callback. All other messages chain to libui. */
static LRESULT CALLBACK parent_subclass(HWND hwnd, UINT msg, WPARAM wp, LPARAM lp) {
    if (msg == WM_COMMAND && g_hwnd && (HWND)lp == g_hwnd) {
        if (HIWORD(wp) == EN_CHANGE) {
            fire_notify();
            return 0;
        }
    }
    return CallWindowProc(g_old_parent_proc, hwnd, msg, wp, lp);
}

/* ── Public C API (same symbols as ime_bridge.m / ime_bridge_linux.c) ────── */

__declspec(dllexport)
void ime_create_textview(void* area_ns_view, double x, double y, double w, double h,
                         int vcenter, double font_size, const char* initial_text) {
    HWND parent = (HWND)area_ns_view;
    if (!parent || !IsWindow(parent)) {
        fprintf(stderr, "[IME] win: invalid parent handle\n");
        return;
    }

    /* Destroy any previous overlay first (defensive). */
    if (g_hwnd) ime_destroy_textview();

    g_parent = parent;

    DWORD style = WS_CHILD | WS_VISIBLE | ES_LEFT | WS_TABSTOP;
    if (vcenter == 0) {
        style |= ES_MULTILINE | ES_AUTOVSCROLL | ES_WANTRETURN;
    }

    g_hwnd = CreateWindowExW(
        0, L"EDIT", L"",
        style,
        (int)x, (int)y, (int)w, (int)h,
        parent, (HMENU)(INT_PTR)g_ctrl_id, GetModuleHandle(NULL), NULL);

    if (!g_hwnd) {
        fprintf(stderr, "[IME] win: CreateWindowEx failed\n");
        g_parent = NULL;
        return;
    }

    /* Font sized to match the renderer's single-line field font. The handle is
     * kept in g_font and freed on destroy — deleting it here would break the
     * EDIT control's rendering. */
    HDC dc = GetDC(NULL);
    int dpi = dc ? GetDeviceCaps(dc, LOGPIXELSY) : 96;
    if (dc) ReleaseDC(NULL, dc);
    int hpx = -(int)(font_size * (double)dpi / 72.0 + 0.5);
    if (hpx >= 0) hpx = -hpx;
    if (g_font) {
        DeleteObject(g_font);
        g_font = NULL;
    }
    g_font = CreateFontW(hpx, 0, 0, 0, FW_NORMAL, FALSE, FALSE, FALSE,
                         DEFAULT_CHARSET, OUT_DEFAULT_PRECIS, CLIP_DEFAULT_PRECIS,
                         DEFAULT_QUALITY, DEFAULT_PITCH | FF_DONTCARE, L"Segoe UI");
    if (g_font) {
        SendMessageW(g_hwnd, WM_SETFONT, (WPARAM)g_font, TRUE);
    }

    /* Initial text (UTF-8 → UTF-16). */
    wchar_t* winit = utf8_to_wcs(initial_text && *initial_text ? initial_text : "");
    SetWindowTextW(g_hwnd, winit ? winit : L"");
    free(winit);

    /* Subclass both the edit and the parent (to receive EN_CHANGE). */
    g_old_edit_proc = (WNDPROC)SetWindowLongPtr(g_hwnd, GWLP_WNDPROC, (LONG_PTR)edit_subclass);
    g_old_parent_proc = (WNDPROC)SetWindowLongPtr(parent, GWLP_WNDPROC, (LONG_PTR)parent_subclass);

    g_composing = FALSE;
}

__declspec(dllexport)
void ime_destroy_textview(void) {
    /* Restore subclassing BEFORE destroying so the final messages route to
     * the original (libui) window procedures. */
    if (g_hwnd && g_old_edit_proc) {
        SetWindowLongPtr(g_hwnd, GWLP_WNDPROC, (LONG_PTR)g_old_edit_proc);
    }
    if (g_parent && g_old_parent_proc) {
        SetWindowLongPtr(g_parent, GWLP_WNDPROC, (LONG_PTR)g_old_parent_proc);
    }
    if (g_hwnd) {
        DestroyWindow(g_hwnd);
    }
    if (g_font) {
        DeleteObject(g_font);
        g_font = NULL;
    }
    g_hwnd = NULL;
    g_parent = NULL;
    g_old_edit_proc = NULL;
    g_old_parent_proc = NULL;
    g_notify_callback = NULL;
    g_tab_callback = NULL;
    g_composing = FALSE;
}

__declspec(dllexport)
void ime_set_notify_callback(ime_notify_callback_t callback) {
    g_notify_callback = callback;
}

__declspec(dllexport)
void ime_clear_notify_callback(void) {
    g_notify_callback = NULL;
}

__declspec(dllexport)
void ime_set_tab_callback(ime_tab_callback_t callback) {
    g_tab_callback = callback;
}

__declspec(dllexport)
void ime_clear_tab_callback(void) {
    g_tab_callback = NULL;
}

__declspec(dllexport)
void* ime_get_textview(void) {
    return (void*)g_hwnd;
}

__declspec(dllexport)
int ime_has_textview(void) {
    return g_hwnd != NULL ? 1 : 0;
}

__declspec(dllexport)
void ime_set_text(const char* text) {
    if (!g_hwnd) return;
    wchar_t* w = utf8_to_wcs(text && *text ? text : "");
    SetWindowTextW(g_hwnd, w ? w : L"");
    free(w);
    /* Move caret to end. */
    SendMessageW(g_hwnd, EM_SETSEL, (WPARAM)-1, (LPARAM)-1);
}

__declspec(dllexport)
int ime_get_caret_position(void) {
    if (!g_hwnd) return 0;
    DWORD start = 0, end = 0;
    SendMessageW(g_hwnd, EM_GETSEL, (WPARAM)&start, (LPARAM)&end);
    return (int)start;
}

__declspec(dllexport)
void ime_set_caret_position(int pos) {
    if (!g_hwnd) return;
    SendMessageW(g_hwnd, EM_SETSEL, (WPARAM)pos, (LPARAM)pos);
}

__declspec(dllexport)
int ime_is_composing(void) {
    return g_composing ? 1 : 0;
}

__declspec(dllexport)
int ime_make_textview_first_responder(void) {
    if (!g_hwnd) return 0;
    HWND prev = SetFocus(g_hwnd);
    (void)prev;
    return 1;
}

__declspec(dllexport)
void ime_clear_textview_first_responder(void) {
    if (g_parent && IsWindow(g_parent)) {
        SetFocus(g_parent);
    }
}

__declspec(dllexport)
void ime_set_view_frame(void* view, double x, double y, double w, double h) {
    (void)view;
    if (!g_hwnd) return;
    MoveWindow(g_hwnd, (int)x, (int)y, (int)w, (int)h, TRUE);
}
