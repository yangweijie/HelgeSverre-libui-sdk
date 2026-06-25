/**
 * webview_bridge_linux.c — libui ↔ WebView embed bridge (Linux / GTK)
 *
 * Same architecture as the macOS bridge (webview_bridge.m):
 *   - Create a borderless child GtkWindow (GTK_WINDOW_POPUP)
 *   - Set it transient for the parent GtkWindow (follows parent movement)
 *   - Call webview_create() with the child window as parent
 *   - webview_create() puts WebKitGTK inside the child window
 *
 * Coordinate system:
 *   PHP passes (x, y) from top-left of the parent widget's allocation.
 *   GTK uses (x, y) from top-left of the parent window's origin for
 *   popup windows.  We translate by adding the parent window's position.
 *
 * Compile:
 *   gcc -shared -fPIC webview_bridge_linux.c \
 *       $(pkg-config --cflags --libs gtk+-3.0) \
 *       -o webview_bridge.so
 *
 *   The webview library (libwebview-webkit.so / PebView.so) must be
 *   loaded separately via PHP FFI.
 */

#include <gtk/gtk.h>
#include <stdint.h>
#include <stdlib.h>

/* ---------------------------------------------------------------------------
 * PebView/webview C API — symbols resolved by linking or dlopen
 * ------------------------------------------------------------------------ */
extern void *webview_create(int debug, void *window);
extern void *webview_get_window(void *wv);
extern int   webview_destroy(void *wv);

/* ---------------------------------------------------------------------------
 * Helper: extract GtkWindow* from a libui handle
 *
 * libui on GTK returns a GtkWidget* from uiControlHandle().
 * For a uiWindow, this is the GtkWindow itself.
 * For other controls, it's the underlying widget — we walk up to
 * the toplevel GtkWindow.
 * ------------------------------------------------------------------------ */
static GtkWindow *find_parent_window(uintptr_t parent_handle) {
    GtkWidget *widget = GTK_WIDGET((void *)parent_handle);
    if (!widget) return NULL;

    /* If it's already a window, return it */
    if (GTK_IS_WINDOW(widget)) {
        return GTK_WINDOW(widget);
    }

    /* Walk up the widget tree to find the toplevel GtkWindow */
    GtkWidget *toplevel = gtk_widget_get_toplevel(widget);
    if (GTK_IS_WINDOW(toplevel)) {
        return GTK_WINDOW(toplevel);
    }
    return NULL;
}

/* ---------------------------------------------------------------------------
 * Bridge API — all functions are plain C, callable via PHP FFI
 * ------------------------------------------------------------------------ */

/**
 * wvb_create — Create an embedded webview within a libui window.
 *
 * @param debug              Non-zero to enable Web Inspector.
 * @param parent_handle      Result of uiControlHandle(libui_control).
 *                           For a uiWindow this is the GtkWindow*.
 * @param x, y               Top-left corner in the parent's coordinate space
 *                           (top-left origin, same as CSS).
 * @param w, h               Width and height in pixels.
 * @return webview_t pointer, or NULL on failure.
 */
__attribute__((visibility("default")))
void *wvb_create(int debug, uintptr_t parent_handle,
                 int x, int y, int w, int h) {
    GtkWindow *parent_win = find_parent_window(parent_handle);
    if (!parent_win) return NULL;

    /* Create a borderless popup window */
    GtkWidget *child_win = gtk_window_new(GTK_WINDOW_POPUP);
    gtk_window_set_decorated(GTK_WINDOW(child_win), FALSE);
    gtk_window_set_transient_for(GTK_WINDOW(child_win), parent_win);

    /* Position relative to parent window's origin */
    gint px, py;
    gtk_window_get_position(parent_win, &px, &py);
    gtk_window_move(GTK_WINDOW(child_win), px + x, py + y);
    gtk_widget_set_size_request(child_win, w, h);

    /* Create the webview inside the child window.
     * webview_create() will call gtk_container_add() and set the webview
     * as the child window's content, identical to how it works on macOS. */
    void *wv = webview_create(debug, child_win);
    if (!wv) {
        gtk_widget_destroy(child_win);
        return NULL;
    }

    gtk_widget_show_all(child_win);
    return wv;
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
    if (!wv) return;

    GtkWindow *parent_win = find_parent_window(parent_handle);
    if (!parent_win) return;

    GtkWindow *child_win = (GtkWindow *)webview_get_window(wv);
    if (!child_win) return;

    /* Recalculate position relative to parent window's origin */
    gint px, py;
    gtk_window_get_position(parent_win, &px, &py);
    gtk_window_move(child_win, px + x, py + y);
    gtk_window_resize(child_win, w, h);
}

/**
 * wvb_destroy — Destroy an embedded webview and clean up the child window.
 *
 * 1. webview_destroy() — WebKitGTK widget released
 * 2. gtk_widget_destroy() — child popup window freed
 */
__attribute__((visibility("default")))
void wvb_destroy(void *wv) {
    if (!wv) return;

    GtkWindow *child_win = (GtkWindow *)webview_get_window(wv);
    webview_destroy(wv);

    if (child_win) {
        gtk_widget_destroy(GTK_WIDGET(child_win));
    }
}
