/*
 * audio.c — miniaudio-based cross-platform audio playback bridge for PHP FFI.
 *
 * Exposes a tiny C API that uses opaque 1-based integer handles so PHP never
 * touches a raw `ma_sound*` pointer (FFI-safe). Build it as a shared library
 * (audio.dylib / libaudio.so / audio.dll) and load it from PHP via
 * \FFI::cdef() (see src/System/Audio.php).
 *
 * Lifecycle (per process):
 *   audio_init()      -> ma_engine_init (idempotent)
 *   audio_load()      -> ma_sound_init_from_file  (returns 1-based handle)
 *   audio_play/resume/pause/stop/set_volume/set_looping/is_playing
 *   audio_unload()    -> ma_sound_uninit + free
 *   audio_shutdown()  -> ma_engine_uninit (idempotent, frees all sounds)
 *
 * Build (macOS):
 *   clang -shared -fPIC -DMINIAUDIO_IMPLEMENTATION audio.c \
 *     -framework CoreFoundation -framework AudioToolbox -framework AudioUnit \
 *     -o audio.dylib
 *
 * Build (Linux):
 *   gcc -shared -fPIC -DMINIAUDIO_IMPLEMENTATION audio.c -lasound -lpthread \
 *     -o libaudio.so
 *
 * Build (Windows):
 *   cl /LD /DMINIAUDIO_IMPLEMENTATION audio.c /Fe:audio.dll
 *
 * Requires the single-file header miniaudio.h next to this source file.
 */

#ifndef MINIAUDIO_IMPLEMENTATION
#define MINIAUDIO_IMPLEMENTATION
#endif
#include "miniaudio.h"

#define MAX_SLOTS 64

static ma_engine  g_engine;
static int        g_initialized = 0;
static ma_sound*  g_slots[MAX_SLOTS] = {0};

/* ------------------------------------------------------------------ */
/* Lifecycle                                                           */
/* ------------------------------------------------------------------ */

int audio_init(void)
{
    if (g_initialized) {
        return 1;
    }
    ma_result r = ma_engine_init(NULL, &g_engine);
    if (r != MA_SUCCESS) {
        return 0;
    }
    g_initialized = 1;
    return 1;
}

void audio_shutdown(void)
{
    if (!g_initialized) {
        return;
    }
    for (int i = 0; i < MAX_SLOTS; i++) {
        if (g_slots[i] != NULL) {
            ma_sound_uninit(g_slots[i]);
            free(g_slots[i]);
            g_slots[i] = NULL;
        }
    }
    ma_engine_uninit(&g_engine);
    g_initialized = 0;
}

/* ------------------------------------------------------------------ */
/* Internal helpers                                                   */
/* ------------------------------------------------------------------ */

static ma_sound* slot_get(int handle)
{
    if (handle < 1 || handle > MAX_SLOTS) {
        return NULL;
    }
    return g_slots[handle - 1];
}

/* ------------------------------------------------------------------ */
/* Sound management                                                   */
/* ------------------------------------------------------------------ */

int audio_load(const char* path)
{
    if (!g_initialized) {
        return 0;
    }

    int idx = -1;
    for (int i = 0; i < MAX_SLOTS; i++) {
        if (g_slots[i] == NULL) {
            idx = i;
            break;
        }
    }
    if (idx < 0) {
        return 0; /* slot table full */
    }

    ma_sound* s = (ma_sound*) malloc(sizeof(ma_sound));
    if (s == NULL) {
        return 0;
    }

    ma_result r = ma_sound_init_from_file(&g_engine, path, 0, NULL, NULL, s);
    if (r != MA_SUCCESS) {
        free(s);
        return 0;
    }

    g_slots[idx] = s;
    return idx + 1; /* 1-based handle */
}

void audio_unload(int handle)
{
    ma_sound* s = slot_get(handle);
    if (s != NULL) {
        ma_sound_uninit(s);
        free(s);
        g_slots[handle - 1] = NULL;
    }
}

/* Play from the beginning: stop (if running), seek to frame 0, set looping,
 * then start. Returns 1 on success, 0 on failure / invalid handle. */
int audio_play(int handle, int loop)
{
    ma_sound* s = slot_get(handle);
    if (s == NULL) {
        return 0;
    }
    ma_sound_stop(s);
    ma_sound_seek_to_pcm_frame(s, 0);
    ma_sound_set_looping(s, loop ? MA_TRUE : MA_FALSE);
    return ma_sound_start(s) == MA_SUCCESS ? 1 : 0;
}

/* Continue from the current playback position (no seek). */
void audio_resume(int handle)
{
    ma_sound* s = slot_get(handle);
    if (s != NULL) {
        ma_sound_start(s);
    }
}

/* Pause: stop playback but keep the current position. */
void audio_pause(int handle)
{
    ma_sound* s = slot_get(handle);
    if (s != NULL) {
        ma_sound_stop(s);
    }
}

/* Stop: same as pause at the engine level (position retained). Calling
 * audio_play() afterwards restarts from frame 0. */
void audio_stop(int handle)
{
    ma_sound* s = slot_get(handle);
    if (s != NULL) {
        ma_sound_stop(s);
    }
}

void audio_set_volume(int handle, float volume)
{
    ma_sound* s = slot_get(handle);
    if (s != NULL) {
        ma_sound_set_volume(s, volume);
    }
}

void audio_set_looping(int handle, int loop)
{
    ma_sound* s = slot_get(handle);
    if (s != NULL) {
        ma_sound_set_looping(s, loop ? MA_TRUE : MA_FALSE);
    }
}

int audio_is_playing(int handle)
{
    ma_sound* s = slot_get(handle);
    if (s == NULL) {
        return 0;
    }
    return ma_sound_is_playing(s) == MA_TRUE ? 1 : 0;
}
