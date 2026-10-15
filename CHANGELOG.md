# Changelog

All notable changes to local_scormcompress are documented in this file.

## 1.0.2 (2026-10-15)

- Restore exact-artifact release evidence with a new immutable release identity.
- Align packaged release metadata and documentation.
- Preserve runtime code byte-for-byte from the distributed 1.0.0 archive.
- No runtime features, behaviour, requirements or pricing changes.

## 1.0.0 (2026-08-11)

- Initial release.
- Background compression of SCORM package media on upload and update.
- Image compression (JPEG, PNG, WebP) via ImageMagick with a PHP GD fallback.
- Video compression (H.264/AAC, and VP9/Opus for WebM) via ffmpeg.
- Audio compression (MP3, AAC/M4A, OGG, Opus) via ffmpeg.
- Balanced default quality settings, all configurable in admin.
- Master enable/disable switch, per-media-type toggles, size and dimension caps.
- Auto-detection of ffmpeg/ffprobe/ImageMagick with a status readout, plus
  explicit path overrides.
- Idempotent processing (never recompresses its own output).
- Optional retention of the original package.
- Admin compression report with per-package and total storage savings.
- Null privacy provider (no personal data stored).
- Support for Moodle 4.1 LTS through 5.0.
