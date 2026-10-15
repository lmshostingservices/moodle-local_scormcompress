# SCORM Compress (local_scormcompress)

Automatically compress the images, audio and video inside SCORM packages as they
are uploaded to Moodle, so activities stay clear on screen while taking up far
less storage and downloading faster for learners.

- **Supported Moodle versions:** 4.1 LTS → 5.0 (branch 401–500)
- **Plugin type:** `local`
- **Frankenstyle name:** `local_scormcompress`

## Release identity

Release: 1.0.2. Numeric version: 2026101501. Date: 2026-10-15.

This metadata-only replacement preserves the runtime code from 1.0.0.
It restores release verification without introducing functional changes.

## What it does

When a SCORM activity is created (or updated with a new package), the plugin
queues a background task. That task:

1. Reads the uploaded SCORM ZIP from the activity's file area.
2. Unpacks it to a temporary working directory.
3. Re-encodes each supported media file at balanced, configurable quality:
   - **Images** (JPEG, PNG, WebP) via ImageMagick, or PHP GD as a fallback.
   - **Video** (MP4, MOV, M4V, AVI, MKV, FLV, WMV → H.264/AAC; WebM → VP9/Opus) via ffmpeg.
   - **Audio** (MP3, M4A/AAC, OGG, Opus) via ffmpeg.
4. Repackages the ZIP, but only keeps the result if it is genuinely smaller.
5. Replaces the package and asks SCORM to re-extract it (revision is bumped
   automatically, so the player picks up the new content).

File names and folder structure are preserved, so internal references inside the
SCORM package (HTML, manifest, JS) continue to work. A media file is only
replaced when its re-encoded version is actually smaller than the original.

Processing happens **in the background via cron**, so large, video-heavy uploads
never time out the web request. Each package is processed once — the plugin
records the content hash of its own output and will not recompress it on later
edits.

## Requirements

- Moodle 4.1 or later (PHP 7.4+ on 4.1; PHP 8.2+ on Moodle 5.0).
- **ffmpeg** and **ffprobe** on the server for audio/video compression.
- **ImageMagick** (`magick` or `convert`) recommended for images; PHP **GD** is
  used automatically if ImageMagick is not present.
- The ability to launch external processes (`proc_open` not disabled) for
  ffmpeg/ImageMagick. Without it, only GD image compression is available.
- Moodle **cron** must be running (as it should be on any production site).

The settings page shows a live status readout of which tools were detected.

## Installation

1. Copy the plugin into `local/scormcompress` in your Moodle directory, or
   install the ZIP via **Site administration → Plugins → Install plugins**.
2. Complete the upgrade when prompted.
3. Visit **Site administration → Plugins → Local plugins → SCORM Compress** to
   review the settings and confirm the media tools were detected.

If ffmpeg/ImageMagick are installed in a non-standard location, set their full
paths in the **Tool paths** section.

## Settings

| Setting | Default | Purpose |
| --- | --- | --- |
| Enable compression | On | Master switch. |
| Compress on update | On | Also process when a new package replaces an old one. |
| Keep a backup of the original | Off | Retain the original package as a safety net (uses extra storage). |
| Image quality | 82 | JPEG/WebP quality (1–100). |
| Maximum image dimension | 1600 px | Downscale larger images (0 = keep original). |
| Video quality (CRF) | 23 | H.264 constant rate factor (lower = higher quality). |
| Maximum video height | 720 px | Downscale taller video (0 = keep original). |
| Encoding speed preset | medium | ffmpeg x264 preset. |
| Audio bitrate | 128 kbps | Target audio bitrate. |
| Minimum media file size | 50 KB | Ignore tiny files. |
| Maximum package size | 0 (no limit) | Skip very large packages. |
| Log retention | 365 days | Auto-prune old report entries. |

## Reports

**Site administration → Plugins → Local plugins → SCORM Compress → Compression
report** shows every processed package, how many media files were re-encoded,
original vs compressed size, and total storage saved. Access is controlled by
the `local/scormcompress:viewreport` capability (managers by default).

## Privacy

The plugin stores no personal data. It only records file-size statistics per
SCORM package (see the privacy provider).

## How it works technically

- Integration is via event observers on `\core\event\course_module_created` and
  `\core\event\course_module_updated` (see `db/events.php`), filtered to the
  `scorm` module. This is the supported extension point on both Moodle 4.x and
  5.x — there is no core hook for module save.
- The observer queues a deduplicated `\core\task\adhoc_task`.
- The task uses the File API to swap the package and calls `scorm_parse()` so
  Moodle re-extracts the content and increments the revision itself.

## License

2026 Jamie. Licensed under the GNU GPL v3 or later. See [LICENSE](LICENSE).
