/**
 * context_menu_linux.c — Native popup context menu bridge (Linux/GTK)
 *
 * Shows a GTK popup menu at the current mouse pointer location.
 *
 * Compile:
 *   gcc -shared -fPIC \
 *       context_menu_linux.c \
 *       $(pkg-config --cflags --libs gtk+-3.0) \
 *       -o libcontext_menu.so
 */

#include <gtk/gtk.h>
#include <string.h>
#include <jansson.h>

/**
 * Show a native popup context menu.
 *
 * @param menu_json  JSON array of menu item objects.
 * @param x, y       Ignored on Linux (uses pointer location).
 * @return int       Index of selected item, or -1 if cancelled.
 */
__attribute__((visibility("default")))
int cm_show_menu(const char *menu_json, double x, double y) {
    // Initialize GTK if not already done
    if (!gtk_init_check(NULL, NULL)) {
        return -1;
    }

    json_error_t err;
    json_t *root = json_loads(menu_json, 0, &err);
    if (!root || !json_is_array(root)) {
        if (root) json_decref(root);
        return -1;
    }

    GtkWidget *menu = gtk_menu_new();
    int item_count = (int)json_array_size(root);
    int selected = -1;

    for (int i = 0; i < item_count; i++) {
        json_t *item = json_array_get(root, i);
        if (!json_is_object(item)) continue;

        const char *text = json_string_value(json_object_get(item, "text"));
        if (!text) text = "";

        if (strcmp(text, "-") == 0) {
            gtk_menu_shell_append(GTK_MENU_SHELL(menu), gtk_separator_menu_item_new());
            continue;
        }

        GtkWidget *menu_item = gtk_menu_item_new_with_label(text);
        json_t *disabled = json_object_get(item, "disabled");
        json_t *checked  = json_object_get(item, "checked");

        gtk_widget_set_sensitive(menu_item, !disabled || !json_is_true(disabled));

        if (checked && json_is_true(checked)) {
            // Use check menu item
            GtkWidget *check_item = gtk_check_menu_item_new_with_label(text);
            gtk_check_menu_item_set_active(GTK_CHECK_MENU_ITEM(check_item), TRUE);
            gtk_widget_set_sensitive(check_item, !disabled || !json_is_true(disabled));
            gtk_menu_shell_append(GTK_MENU_SHELL(menu), check_item);

            // Store index as data
            int *idx = g_malloc(sizeof(int));
            *idx = i;
            g_object_set_data_full(G_OBJECT(check_item), "menu-idx", idx, g_free);
            g_signal_connect(check_item, "activate", G_CALLBACK(+[](GtkWidget *w, gpointer data) {
                int *idx = (int *)g_object_get_data(G_OBJECT(w), "menu-idx");
                if (idx) {
                    // Signal the main loop to quit with this index
                    g_object_set_data(G_OBJECT(gtk_widget_get_ancestor(w, GTK_TYPE_MENU)), "selected-idx", GINT_TO_POINTER(*idx));
                }
                gtk_main_quit();
            }), NULL);
            continue;
        }

        gtk_menu_shell_append(GTK_MENU_SHELL(menu), menu_item);

        // Store index as data
        int *idx = g_malloc(sizeof(int));
        *idx = i;
        g_object_set_data_full(G_OBJECT(menu_item), "menu-idx", idx, g_free);

        g_signal_connect(menu_item, "activate", G_CALLBACK(+[](GtkWidget *w, gpointer data) {
            int *idx = (int *)g_object_get_data(G_OBJECT(w), "menu-idx");
            if (idx) {
                GtkWidget *parent_menu = gtk_widget_get_ancestor(w, GTK_TYPE_MENU);
                if (parent_menu) {
                    g_object_set_data(G_OBJECT(parent_menu), "selected-idx", GINT_TO_POINTER(*idx));
                }
            }
            gtk_main_quit();
        }), NULL);
    }

    gtk_widget_show_all(menu);

    // Show the menu at the pointer location
    gtk_menu_popup_at_pointer(GTK_MENU(menu), NULL);

    // Block until menu is dismissed
    gtk_main();

    // Get the selected index
    gpointer val = g_object_get_data(G_OBJECT(menu), "selected-idx");
    if (val) {
        selected = GPOINTER_TO_INT(val);
    }

    gtk_widget_destroy(menu);
    json_decref(root);

    return selected;
}
