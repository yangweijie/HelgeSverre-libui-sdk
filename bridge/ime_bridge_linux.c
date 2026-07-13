/**
 * ime_bridge_linux.c — Native IME text overlay bridge (Linux / GTK3)
 *
 * Mirrors the macOS ime_bridge.m API surface so the same PHP FFI cdef works
 * on all three platforms. Instead of an NSTextView it uses a GTK entry /
 * text view hosted in a borderless, decorated-less floating GtkWindow
 * positioned over the field's screen rectangle. GTK widgets handle IME
 * composition (via their internal GtkIMContext) natively, exactly like the
 * macOS overlay.
 *
 * Single-line fields (vcenter != 0) get a GtkEntry; multi-line fields
 * (vcenter == 0, e.g. TextArea) get a GtkTextView.
 *
 * Text is exchanged as UTF-8 with PHP, which matches GTK's native encoding.
 *
 * Build:
 *   gcc -shared -fPIC bridge/ime_bridge_linux.c \
 *       $(pkg-config --cflags --libs gtk+-3.0) \
 *       -o bridge/ime_bridge.so
 *
 * Requires: libgtk-3-dev (Ubuntu/Debian: sudo apt install libgtk-3-dev)
 */

#include <gtk/gtk.h>
#include <gdk/gdk.h>
#include <string.h>
#include <stdlib.h>
#include <stdio.h>

/* The live editor widget (GtkEntry or GtkTextView) and its floating window. */
static GtkWidget* g_widget = NULL;
static GtkWidget* g_popup = NULL;
static GtkWidget* g_parent = NULL;

/* TRUE when the overlay is a multi-line GtkTextView. */
static gboolean g_multiline = FALSE;

/* Best-effort IME composition flag (GTK3 entry has no public composing
 * signal; we keep it FALSE and rely on the commit notify callback). */
static gboolean g_composing = FALSE;

/* PHP-provided callbacks. */
typedef void (*ime_notify_callback_t)(const char*, int);
typedef void (*ime_tab_callback_t)(int);
static ime_notify_callback_t g_notify_callback = NULL;
static ime_tab_callback_t g_tab_callback = NULL;

/* ── Helpers ─────────────────────────────────────────────────────────────── */

/* Forward declaration — ime_create_textview may tear down a previous overlay. */
static void ime_destroy_textview(void);

/* Translate an Area-relative (x, y) into absolute screen coordinates. */
static void screen_coords(GtkWidget* parent, double x, double y, gint* out_x, gint* out_y) {
    gint wx = 0, wy = 0;
    GdkWindow* gwin = gtk_widget_get_window(parent);
    if (gwin) gdk_window_get_origin(gwin, &wx, &wy);
    GtkAllocation a;
    gtk_widget_get_allocation(parent, &a);
    *out_x = wx + a.x + (gint)x;
    *out_y = wy + a.y + (gint)y;
}

/* Read current text + caret and fire the notify callback. */
static void fire_notify(void) {
    if (!g_notify_callback || !g_widget) return;

    const char* text;
    int caret;

    if (!g_multiline) {
        text = gtk_entry_get_text(GTK_ENTRY(g_widget));
        caret = gtk_editable_get_position(GTK_EDITABLE(g_widget));
    } else {
        GtkTextBuffer* buf = gtk_text_view_get_buffer(GTK_TEXT_VIEW(g_widget));
        GtkTextIter s, e;
        gtk_text_buffer_get_bounds(buf, &s, &e);
        char* t = gtk_text_buffer_get_text(buf, &s, &e, FALSE);
        GtkTextMark* mark = gtk_text_buffer_get_insert(buf);
        GtkTextIter cur;
        gtk_text_buffer_get_iter_at_mark(buf, &cur, mark);
        caret = gtk_text_iter_get_offset(&cur);
        g_notify_callback(t ? t : "", caret);
        g_free(t);
        return;
    }

    g_notify_callback(text ? text : "", caret);
}

/* ── Signal handlers ─────────────────────────────────────────────────────── */

static void on_changed(GtkWidget* w, gpointer data) {
    (void)w; (void)data;
    fire_notify();
}

static gboolean on_key_press(GtkWidget* w, GdkEventKey* ev, gpointer data) {
    (void)w; (void)data;
    if (ev->keyval == GDK_KEY_Tab || ev->keyval == GDK_KEY_ISO_Left_Tab) {
        if (g_tab_callback) {
            g_tab_callback(ev->keyval == GDK_KEY_ISO_Left_Tab ? 1 : 0);
        }
        return TRUE; /* swallow Tab, don't move focus */
    }
    return FALSE;
}

/* ── Public C API (same symbols as ime_bridge.m / ime_bridge_win.c) ──────── */

__attribute__((visibility("default")))
void ime_create_textview(void* area_ns_view, double x, double y, double w, double h,
                         int vcenter, double font_size, const char* initial_text) {
    GtkWidget* parent = (GtkWidget*)area_ns_view;
    if (!parent) {
        fprintf(stderr, "[IME] linux: invalid parent widget\n");
        return;
    }

    /* Destroy any previous overlay first (defensive). */
    if (g_widget) ime_destroy_textview();

    g_parent = parent;

    /* libui already initialized GTK; this is a safe no-op if so. */
    gtk_init_check(0, NULL);

    gint sx = 0, sy = 0;
    screen_coords(parent, x, y, &sx, &sy);

    g_popup = gtk_window_new(GTK_WINDOW_TOPLEVEL);
    gtk_window_set_decorated(GTK_WINDOW(g_popup), FALSE);
    gtk_window_set_skip_taskbar_hint(GTK_WINDOW(g_popup), TRUE);
    gtk_window_set_type_hint(GTK_WINDOW(g_popup), GDK_WINDOW_TYPE_HINT_POPUP_MENU);
    GtkWidget* top = gtk_widget_get_toplevel(parent);
    if (GTK_IS_WINDOW(top)) {
        gtk_window_set_transient_for(GTK_WINDOW(g_popup), GTK_WINDOW(top));
    }

    g_multiline = (vcenter == 0);
    if (!g_multiline) {
        g_widget = gtk_entry_new();
    } else {
        g_widget = gtk_text_view_new();
    }

    /* Font sized to match the renderer's single-line field font. Use a CSS
     * provider (gtk_widget_override_font is deprecated since 3.16). */
    GtkCssProvider* prov = gtk_css_provider_new();
    char* css = g_strdup_printf(
        "entry, textview { font: %dpx \"Sans\"; }", (int)(font_size + 0.5));
    gtk_css_provider_load_from_data(prov, css, -1, NULL);
    g_free(css);
    GtkStyleContext* ctx = gtk_widget_get_style_context(g_widget);
    gtk_style_context_add_provider(
        ctx, GTK_STYLE_PROVIDER(prov), GTK_STYLE_PROVIDER_PRIORITY_APPLICATION);
    g_object_unref(prov);

    if (initial_text && *initial_text) {
        if (!g_multiline) {
            gtk_entry_set_text(GTK_ENTRY(g_widget), initial_text);
        } else {
            gtk_text_buffer_set_text(gtk_text_view_get_buffer(GTK_TEXT_VIEW(g_widget)),
                                     initial_text, -1);
        }
    }

    gtk_container_add(GTK_CONTAINER(g_popup), g_widget);
    gtk_widget_show_all(g_popup);
    gtk_window_resize(GTK_WINDOW(g_popup), (gint)w, (gint)h);
    gtk_window_move(GTK_WINDOW(g_popup), sx, sy);

    g_signal_connect(g_widget, "changed", G_CALLBACK(on_changed), NULL);
    g_signal_connect(g_widget, "key-press-event", G_CALLBACK(on_key_press), NULL);

    gtk_widget_grab_focus(g_widget);
}

__attribute__((visibility("default")))
void ime_destroy_textview(void) {
    if (g_popup) {
        gtk_widget_destroy(g_popup);
    }
    g_popup = NULL;
    g_widget = NULL;
    g_parent = NULL;
    g_notify_callback = NULL;
    g_tab_callback = NULL;
    g_composing = FALSE;
    g_multiline = FALSE;
}

__attribute__((visibility("default")))
void ime_set_notify_callback(ime_notify_callback_t callback) {
    g_notify_callback = callback;
}

__attribute__((visibility("default")))
void ime_clear_notify_callback(void) {
    g_notify_callback = NULL;
}

__attribute__((visibility("default")))
void ime_set_tab_callback(ime_tab_callback_t callback) {
    g_tab_callback = callback;
}

__attribute__((visibility("default")))
void ime_clear_tab_callback(void) {
    g_tab_callback = NULL;
}

__attribute__((visibility("default")))
void* ime_get_textview(void) {
    return (void*)g_widget;
}

__attribute__((visibility("default")))
int ime_has_textview(void) {
    return g_widget != NULL ? 1 : 0;
}

__attribute__((visibility("default")))
void ime_set_text(const char* text) {
    if (!g_widget) return;
    if (!text) text = "";
    if (!g_multiline) {
        gtk_entry_set_text(GTK_ENTRY(g_widget), text);
    } else {
        gtk_text_buffer_set_text(gtk_text_view_get_buffer(GTK_TEXT_VIEW(g_widget)),
                                 text, -1);
    }
}

__attribute__((visibility("default")))
int ime_get_caret_position(void) {
    if (!g_widget) return 0;
    if (!g_multiline) {
        return gtk_editable_get_position(GTK_EDITABLE(g_widget));
    }
    GtkTextBuffer* buf = gtk_text_view_get_buffer(GTK_TEXT_VIEW(g_widget));
    GtkTextMark* mark = gtk_text_buffer_get_insert(buf);
    GtkTextIter cur;
    gtk_text_buffer_get_iter_at_mark(buf, &cur, mark);
    return gtk_text_iter_get_offset(&cur);
}

__attribute__((visibility("default")))
void ime_set_caret_position(int pos) {
    if (!g_widget) return;
    if (!g_multiline) {
        gtk_editable_set_position(GTK_EDITABLE(g_widget), pos);
    } else {
        GtkTextBuffer* buf = gtk_text_view_get_buffer(GTK_TEXT_VIEW(g_widget));
        GtkTextIter cur;
        gtk_text_buffer_get_iter_at_offset(buf, &cur, pos);
        gtk_text_buffer_place_cursor(buf, &cur);
    }
}

__attribute__((visibility("default")))
int ime_is_composing(void) {
    return g_composing ? 1 : 0;
}

__attribute__((visibility("default")))
int ime_make_textview_first_responder(void) {
    if (!g_widget) return 0;
    gtk_widget_grab_focus(g_widget);
    return 1;
}

__attribute__((visibility("default")))
void ime_clear_textview_first_responder(void) {
    if (g_parent) {
        gtk_widget_grab_focus(g_parent);
    }
}

__attribute__((visibility("default")))
void ime_set_view_frame(void* view, double x, double y, double w, double h) {
    (void)view;
    if (!g_popup || !g_parent) return;
    gint sx = 0, sy = 0;
    screen_coords(g_parent, x, y, &sx, &sy);
    gtk_window_move(GTK_WINDOW(g_popup), sx, sy);
    gtk_window_resize(GTK_WINDOW(g_popup), (gint)w, (gint)h);
}
