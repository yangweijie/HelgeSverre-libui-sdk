/**
 * webview_bridge_win.c — libui ↔ WebView embed bridge (Windows / Win32)
 *
 * Same architecture as the macOS bridge (webview_bridge.m):
 *   - Create a child HWND with WS_CHILD style inside the parent window
 *   - Call webview_create() with the child HWND as parent
 *   - webview_create() puts WebView2/MSHTML inside the child HWND
 *
 * Coordinate system:
 *   PHP passes (x, y) from top-left of the parent's client area.
 *   We use MoveWindow/SetWindowPos to position the child HWND within
 *   the parent's client area — standard Win32 child window behavior.
 *
 * Compile (MSVC):
 *   cl /LD webview_bridge_win.c user32.lib /Fe:webview_bridge.dll
 *
 * Compile (MinGW):
 *   gcc -shared webview_bridge_win.c -o webview_bridge.dll -luser32
 */

#define WIN32_LEAN_AND_MEAN
#include <windows.h>
#include <stdint.h>

/* ---------------------------------------------------------------------------
 * PebView/webview C API — symbols resolved by linking or LoadLibrary
 * ------------------------------------------------------------------------ */
extern void *webview_create(int debug, void *window);
extern void *webview_get_window(void *wv);
extern int   webview_destroy(void *wv);

/* ---------------------------------------------------------------------------
 * Bridge API — all functions are plain C, callable via PHP FFI
 * ------------------------------------------------------------------------ */

/**
 * wvb_create — Create an embedded webview within a libui window.
 *
 * @param debug              Non-zero to enable DevTools.
 * @param parent_handle      Result of uiControlHandle(libui_control).
 *                           For a uiWindow this is the HWND.
 * @param x, y               Top-left corner in the parent's client area
 *                           (top-left origin, same as CSS).
 * @param w, h               Width and height in pixels.
 * @return webview_t pointer, or NULL on failure.
 *
 * The caller retains ownership and MUST call wvb_destroy() before
 * the parent window is destroyed.
 */
__declspec(dllexport)
void *wvb_create(int debug, uintptr_t parent_handle,
                 int x, int y, int w, int h) {
    HWND parent_hwnd = (HWND)(void *)parent_handle;
    if (!parent_hwnd || !IsWindow(parent_hwnd)) return NULL;

    /* Create a child window — WS_CHILD makes it a true child of parent_hwnd.
     * Coordinates are client-area-relative.  No title bar, no border. */
    HWND child_hwnd = CreateWindowExW(
        0,                          /* dwExStyle — no extended styles */
        L"STATIC",                  /* lpClassName — use STATIC control */
        L"",                        /* lpWindowName */
        WS_CHILD | WS_VISIBLE,     /* dwStyle — child, initially visible */
        x, y, w, h,                /* position and size in client coords */
        parent_hwnd,               /* hWndParent */
        (HMENU)(uintptr_t)1,      /* hMenu — non-zero ID */
        NULL,                      /* hInstance — current process */
        NULL                       /* lpParam */
    );

    if (!child_hwnd) return NULL;

    /* Ensure the child HWND is at the top of the z-order so libui's
     * internal rendering doesn't paint over it. */
    SetWindowPos(child_hwnd, HWND_TOP, 0, 0, 0, 0,
                 SWP_NOMOVE | SWP_NOSIZE | SWP_NOACTIVATE);

    /* Create the webview inside the child HWND.
     * PebView creates its own "webview_widget" child window inside our
     * STATIC HWND at 0,0,0,0 and embeds WebView2 there.  It sizes the
     * widget via WM_SIZE on the parent — but our STATIC's default WndProc
     * never forwards that.  So we enumerate the widget and resize it
     * manually after creation. */
    void *wv = webview_create(debug, child_hwnd);
    if (!wv) {
        DestroyWindow(child_hwnd);
        return NULL;
    }

    /* Find PebView's widget child and resize it to fill our STATIC HWND. */
    {
        RECT cr;
        if (GetClientRect(child_hwnd, &cr)) {
            int cw = cr.right - cr.left;
            int ch = cr.bottom - cr.top;

            /* PebView registers "webview_widget" — find it. */
            HWND widget = FindWindowExW(child_hwnd, NULL, L"webview_widget", NULL);
            if (!widget) {
                /* Fallback: grab whatever child exists. */
                widget = GetWindow(child_hwnd, GW_CHILD);
            }
            if (widget) {
                MoveWindow(widget, 0, 0, cw, ch, TRUE);
                /* Trigger WM_SIZE on the widget so PebView resizes WebView2. */
                SendMessage(widget, WM_SIZE, 0, MAKELPARAM(cw, ch));
            }
        }
    }

    return wv;
}

/**
 * wvb_move — Reposition/resize an embedded webview.
 *
 * Call this when the libui layout changes (e.g. window resize) to keep
 * the webview iframe aligned with its placeholder area.
 */
__declspec(dllexport)
void wvb_move(void *wv, uintptr_t parent_handle,
              int x, int y, int w, int h) {
    if (!wv) return;

    HWND child_hwnd = (HWND)webview_get_window(wv);
    if (!child_hwnd || !IsWindow(child_hwnd)) return;

    /* MoveWindow with TRUE redraws the child immediately */
    MoveWindow(child_hwnd, x, y, w, h, TRUE);

    /* Also resize PebView's widget to match. */
    HWND widget = FindWindowExW(child_hwnd, NULL, L"webview_widget", NULL);
    if (!widget) {
        widget = GetWindow(child_hwnd, GW_CHILD);
    }
    if (widget) {
        MoveWindow(widget, 0, 0, w, h, TRUE);
        SendMessage(widget, WM_SIZE, 0, MAKELPARAM(w, h));
    }
}

/**
 * wvb_destroy — Destroy an embedded webview and clean up the child HWND.
 *
 * 1. webview_destroy() — WebView2/MSHTML released
 * 2. DestroyWindow() — child HWND freed
 */
__declspec(dllexport)
void wvb_destroy(void *wv) {
    if (!wv) return;

    HWND child_hwnd = (HWND)webview_get_window(wv);
    webview_destroy(wv);

    if (child_hwnd && IsWindow(child_hwnd)) {
        DestroyWindow(child_hwnd);
    }
}
