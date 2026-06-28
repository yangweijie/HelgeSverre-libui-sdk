#!/usr/bin/env python3
"""Generate TTS audio + timeline.json from script.md using edge-tts."""

import json
import re
import subprocess
import sys
from pathlib import Path

SCRIPT_MD = Path("script.md")
OUT_DIR = Path("_narration")
VOICE = "en-US-AndrewNeural"
GAP = 0.3  # silence between scenes

def parse_script(path):
    """Parse script.md into scenes with cues."""
    text = path.read_text()
    # Remove frontmatter
    text = re.sub(r'^---\n.*?\n---\n', '', text, flags=re.DOTALL)

    scenes = []
    # Split by ## scene-id
    for block in re.split(r'\n##\s+', text):
        block = block.strip()
        if not block:
            continue
        lines = block.split('\n')
        scene_id = lines[0].strip()
        content = '\n'.join(lines[1:]).strip()

        # Split by [[cue:id]]
        parts = re.split(r'\[\[cue:(\w+)\]\]', content)
        chunks = []
        cues = []
        current_text = ""
        i = 0
        while i < len(parts):
            if i % 2 == 0:
                current_text = parts[i].strip()
                if current_text:
                    chunks.append(current_text)
            else:
                cue_id = parts[i]
                i += 1
                next_text = parts[i].strip() if i < len(parts) else ""
                # The cue was found at end of previous chunk
                cues.append({"id": cue_id, "chunk_index": len(chunks)})
                if next_text:
                    chunks.append(next_text)
            i += 1

        scenes.append({
            "id": scene_id,
            "text": ''.join(chunks),
            "chunks": chunks,
            "cues_raw": cues,
        })
    return scenes


def get_mp3_duration(mp3_path):
    """Get duration of mp3 file in seconds using ffprobe."""
    result = subprocess.run(
        ["ffprobe", "-v", "quiet", "-show_entries", "format=duration",
         "-of", "default=noprint_wrappers=1:nokey=1", str(mp3_path)],
        capture_output=True, text=True
    )
    return float(result.stdout.strip())


def main():
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    audio_dir = OUT_DIR / "audio"
    audio_dir.mkdir(exist_ok=True)

    scenes = parse_script(SCRIPT_MD)
    print(f"Parsed {len(scenes)} scenes")

    # Generate audio for each chunk
    cumulative_time = 0.0
    timeline_scenes = []

    for scene_idx, scene in enumerate(scenes):
        scene_id = scene["id"]
        print(f"\n--- {scene_id} ---")

        # Generate individual chunk audio
        chunk_audios = []
        chunk_times = []

        for chunk_idx, chunk_text in enumerate(scene["chunks"]):
            chunk_file = audio_dir / f"{scene_id}_c{chunk_idx}.mp3"
            if not chunk_file.exists():
                print(f"  Generating chunk {chunk_idx}: {chunk_text[:50]}...")
                subprocess.run([
                    "edge-tts",
                    "--voice", VOICE,
                    "--text", chunk_text,
                    "--write-media", str(chunk_file),
                ], check=True)
                print(f"    -> {chunk_file}")

            dur = get_mp3_duration(chunk_file)
            chunk_audios.append(str(chunk_file))
            chunk_times.append(dur)
            print(f"    chunk {chunk_idx}: {dur:.3f}s")

        # Build chunk timeline entries (with absolute times)
        chunk_entries = []
        abs_start = cumulative_time
        for ci, (chunk_file, dur) in enumerate(zip(chunk_audios, chunk_times)):
            chunk_entries.append({
                "text": scene["chunks"][ci],
                "start": sum(chunk_times[:ci]),
                "end": sum(chunk_times[:ci+1]),
                "absoluteStart": abs_start + sum(chunk_times[:ci]),
                "absoluteEnd": abs_start + sum(chunk_times[:ci+1]),
            })
            abs_start += dur  # for next chunk, but we track via sum

        # Map cues to absolute times
        cues_out = []
        for cue in scene["cues_raw"]:
            ci = cue["chunk_index"]
            abs_time = cumulative_time + sum(chunk_times[:ci]) if ci > 0 else cumulative_time
            cues_out.append({
                "id": cue["id"],
                "offset": sum(chunk_times[:ci]) if ci > 0 else 0,
                "absoluteTime": abs_time,
            })

        scene_concat = audio_dir / f"{scene_id}.mp3"
        concat_wav = audio_dir / f"{scene_id}.wav"
        filter_parts = "|".join(p for p in chunk_audios)
        subprocess.run([
            "ffmpeg", "-y",
            "-i", f"concat:{filter_parts}",
            "-acodec", "pcm_s16le", "-ar", "44100", "-ac", "1",
            str(concat_wav),
        ], check=True, capture_output=True)
        subprocess.run([
            "ffmpeg", "-y",
            "-i", str(concat_wav),
            "-codec:a", "libmp3lame", "-qscale:a", "2",
            str(scene_concat),
        ], check=True, capture_output=True)
        concat_wav.unlink()

        scene_duration = sum(chunk_times)
        scene_start = cumulative_time

        timeline_scene = {
            "id": scene_id,
            "start": scene_start,
            "end": scene_start + scene_duration,
            "duration": scene_duration,
            "audio": f"audio/{scene_id}.mp3",
            "text": scene["text"],
            "chunks": chunk_entries,
            "cues": cues_out,
        }
        timeline_scenes.append(timeline_scene)

        cumulative_time += scene_duration + GAP

    # Concatenate all scene audio into voiceover.mp3
    # Use live concat via filter to avoid mp3 concat issues
    scene_mp3s = []
    for scene in timeline_scenes:
        scene_audio = audio_dir / scene["audio"].replace("audio/", "")
        scene_mp3s.append(str(scene_audio.resolve()))
        if GAP > 0:
            gap_mp3 = audio_dir / "gap.mp3"
            if not gap_mp3.exists():
                subprocess.run([
                    "ffmpeg", "-y", "-f", "lavfi", "-i",
                    "anullsrc=r=44100:cl=mono",
                    "-t", str(GAP), str(gap_mp3),
                ], check=True, capture_output=True)
            scene_mp3s.append(str(gap_mp3.resolve()))

    voiceover_wav = OUT_DIR / "voiceover.wav"
    filter_parts = "|".join(scene_mp3s)
    subprocess.run([
        "ffmpeg", "-y",
        "-i", f"concat:{filter_parts}",
        "-acodec", "pcm_s16le", "-ar", "44100", "-ac", "1",
        str(voiceover_wav),
    ], check=True, capture_output=True)

    voiceover_mp3 = OUT_DIR / "voiceover.mp3"
    subprocess.run([
        "ffmpeg", "-y",
        "-i", str(voiceover_wav),
        "-codec:a", "libmp3lame", "-qscale:a", "2",
        str(voiceover_mp3),
    ], check=True, capture_output=True)
    voiceover_wav.unlink()

    total_duration = get_mp3_duration(voiceover_mp3)
    print(f"\nTotal duration: {total_duration:.3f}s")

    # Write timeline.json
    timeline = {
        "title": "yangweijie-ui2-intro",
        "voice": VOICE,
        "speed": 1.0,
        "gap": GAP,
        "totalDuration": total_duration,
        "voiceover": "voiceover.mp3",
        "scenes": timeline_scenes,
    }

    timeline_path = OUT_DIR / "timeline.json"
    timeline_path.write_text(json.dumps(timeline, indent=2, ensure_ascii=False))
    print(f"\nWritten: {timeline_path}")

    # Print summary
    for s in timeline_scenes:
        cue_str = ", ".join(c["id"] for c in s["cues"])
        print(f"  {s['id']:15s} {s['start']:6.2f}-{s['end']:6.2f}s ({s['duration']:.2f}s) cues: {cue_str}")

    print(f"\n✓ Done. Total: {total_duration:.2f}s")


if __name__ == "__main__":
    main()
