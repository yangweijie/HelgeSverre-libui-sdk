/**
 * context_menu_win.c — Native popup context menu bridge (Windows)
 *
 * Shows a Win32 popup menu at the current cursor position.
 *
 * Compile:
 *   cl /LD context_menu_win.c \
 *       /Fe:context_menu.dll \
 *       user32.lib
 */

#include <windows.h>
#include <string.h>
#include <stdlib.h>

/* Simple JSON parser (minimal, no external dependency) */
static const char *json_string_value(const char *json, const char *key, char *buf, int bufsize) {
    if (!json || !key) return NULL;
    char search[256];
    snprintf(search, sizeof(search), "\"%s\"", key);
    const char *p = strstr(json, search);
    if (!p) return NULL;
    p = strchr(p, ':');
    if (!p) return NULL;
    p++;
    while (*p == ' ' || *p == '\t') p++;
    if (*p == '"') {
        p++;
        int i = 0;
        while (*p && *p != '"' && i < bufsize - 1) {
            if (*p == '\\' && *(p+1)) { p++; }
            buf[i++] = *p++;
        }
        buf[i] = '\0';
        return buf;
    }
    return NULL;
}

static int json_bool_value(const char *json, const char *key, int def) {
    if (!json || !key) return def;
    char search[256];
    snprintf(search, sizeof(search), "\"%s\"", key);
    const char *p = strstr(json, search);
    if (!p) return def;
    p = strchr(p, ':');
    if (!p) return def;
    p++;
    while (*p == ' ' || *p == '\t') p++;
    if (strncmp(p, "true", 4) == 0) return 1;
    if (strncmp(p, "false", 5) == 0) return 0;
    return def;
}

/* Extract the Nth item from a JSON array */
static const char *json_array_item(const char *json, int index) {
    if (!json) return NULL;
    const char *p = strchr(json, '[');
    if (!p) return NULL;
    p++;
    int depth = 0;
    int idx = 0;
    while (*p) {
        while (*p == ' ' || *p == '\n' || *p == '\r' || *p == '\t') p++;
        if (*p == '[' || *p == '{') depth++;
        if (*p == ']' || *p == '}') depth--;
        if (*p == '{' && depth == 1) {
            if (idx == index) {
                // Find matching closing brace
                const char *start = p;
                int bdepth = 1;
                p++;
                while (*p && bdepth > 0) {
                    if (*p == '{') bdepth++;
                    if (*p == '}') bdepth--;
                    p++;
                }
                // Return a static buffer (simple approach)
                static char buf[4096];
                int len = (int)(p - start);
                if (len >= 4096) len = 4095;
                strncpy(buf, start, len);
                buf[len] = '\0';
                return buf;
            }
            idx++;
        }
        if (*p) p++;
    }
    return NULL;
}

/**
 * Show a native popup context menu.
 *
 * @param menu_json  JSON array of menu item objects.
 * @param x, y       Screen coordinates (ignored, uses cursor position).
 * @return int       Index of selected item, or -1 if cancelled.
 */
__attribute__((visibility("default")))
int cm_show_menu(const char *menu_json, double x, double y) {
    if (!menu_json) return -1;

    // Count items in the JSON array
    int item_count = 0;
    const char *p = menu_json;
    int depth = 0;
    int brace_depth = 0;
    while (*p) {
        if (*p == '[') depth++;
        if (*p == '{' && depth == 1) {
            item_count++;
            brace_depth = 1;
            p++;
            while (*p && brace_depth > 0) {
                if (*p == '{') brace_depth++;
                if (*p == '}') brace_depth--;
                p++;
            }
            continue;
        }
        if (*p == ']') break;
        p++;
    }

    // Create the popup menu
    HMENU hMenu = CreatePopupMenu();
    if (!hMenu) return -1;

    int *item_index_map = (int *)malloc(item_count * sizeof(int));
    int menu_item_count = 0;

    for (int i = 0; i < item_count; i++) {
        const char *item_json = json_array_item(menu_json, i);
        if (!item_json) continue;

        char text[256];
        if (!json_string_value(item_json, "text", text, sizeof(text))) {
            continue;
        }

        if (strcmp(text, "-") == 0) {
            AppendMenu(hMenu, MF_SEPARATOR, 0, NULL);
            continue;
        }

        int disabled = json_bool_value(item_json, "disabled", 0);
        int checked = json_bool_value(item_json, "checked", 0);

        UINT flags = MF_STRING;
        if (disabled) flags |= MF_GRAYED;
        if (checked)  flags |= MF_CHECKED;

        // Convert UTF-8 to wide char for the menu
        wchar_t wtext[256];
        MultiByteToWideChar(CP_UTF8, 0, text, -1, wtext, 256);

        AppendMenuW(hMenu, flags, i + 1000, wtext);
        item_index_map[menu_item_count++] = i;
    }

    // Get cursor position
    POINT pt;
    GetCursorPos(&pt);

    // Show the popup menu
    int cmd = TrackPopupMenu(hMenu, TPM_RETURNCMD | TPM_NONOTIFY,
                             pt.x, pt.y, 0, NULL, NULL);

    DestroyMenu(hMenu);

    int selected = -1;
    if (cmd >= 1000) {
        int idx = cmd - 1000;
        for (int i = 0; i < menu_item_count; i++) {
            if (item_index_map[i] == idx) {
                selected = idx;
                break;
            }
        }
    }

    free(item_index_map);
    return selected;
}
