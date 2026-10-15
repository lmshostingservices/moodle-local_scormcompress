<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * English language strings for local_scormcompress.
 *
 * @package    local_scormcompress
 * @copyright  2026 Jamie <nct185@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'SCORM Compress';
$string['settings'] = 'Settings';

// Capability.
$string['scormcompress:viewreport'] = 'View the SCORM compression report';

// Privacy.
$string['privacy:metadata'] = 'The SCORM Compress plugin does not store any personal data. It only records file-size statistics for each compressed SCORM package.';

// General settings.
$string['general'] = 'General';
$string['general_desc'] = 'SCORM Compress automatically shrinks the images, audio and video inside SCORM packages after they are uploaded, keeping them clear on screen while reducing storage. Compression runs in the background via scheduled tasks (cron).';
$string['enabled'] = 'Enable compression';
$string['enabled_desc'] = 'When enabled, uploaded SCORM packages are queued for background compression.';
$string['processonupdate'] = 'Compress on update';
$string['processonupdate_desc'] = 'Also compress when a SCORM activity is updated with a new package. Disable to only compress packages when the activity is first created.';
$string['keepbackup'] = 'Keep a backup of the original package';
$string['keepbackup_desc'] = 'Retain a copy of the original, uncompressed package in the plugin file area as a safety net. This roughly doubles storage for processed packages, so leave it off unless you need it.';

// Tool status.
$string['toolstatus'] = 'Detected media tools';
$string['toolstatus_noexec'] = 'This server cannot launch external programs (proc_open is disabled). Only PHP GD image compression will be available; video and audio cannot be compressed.';
$string['toolstatus_found'] = 'Found {$a->tool}: {$a->version}';
$string['toolstatus_missing'] = '{$a} was not found. Set its full path below or install it on the server.';

// Image settings.
$string['images'] = 'Images';
$string['image_enabled'] = 'Compress images';
$string['image_enabled_desc'] = 'Re-encode JPEG, PNG and WebP images. Uses ImageMagick when available, otherwise PHP GD.';
$string['image_quality'] = 'Image quality';
$string['image_quality_desc'] = 'Quality for re-encoded images (1-100). 82 is a good balance of quality and size.';
$string['image_maxdimension'] = 'Maximum image dimension (px)';
$string['image_maxdimension_desc'] = 'Images larger than this on their longest side are scaled down. Set to 0 to keep original dimensions.';

// Video settings.
$string['videos'] = 'Videos';
$string['video_enabled'] = 'Compress videos';
$string['video_enabled_desc'] = 'Re-encode video with ffmpeg (H.264/AAC for MP4/MOV, VP9/Opus for WebM). Requires ffmpeg.';
$string['video_crf'] = 'Video quality (CRF)';
$string['video_crf_desc'] = 'Constant Rate Factor for H.264 (0-51, lower is better quality/larger). 23 is the balanced default.';
$string['video_maxheight'] = 'Maximum video height (px)';
$string['video_maxheight_desc'] = 'Videos taller than this are scaled down, preserving aspect ratio. 720 targets 720p. Set to 0 to keep original height.';
$string['video_preset'] = 'Encoding speed preset';
$string['video_preset_desc'] = 'ffmpeg x264 preset. Slower presets compress a little better but take longer.';

// Audio settings.
$string['audio'] = 'Audio';
$string['audio_enabled'] = 'Compress audio';
$string['audio_enabled_desc'] = 'Re-encode MP3, M4A/AAC, OGG and Opus audio with ffmpeg. Requires ffmpeg.';
$string['audio_bitrate'] = 'Audio bitrate (kbps)';
$string['audio_bitrate_desc'] = 'Target bitrate for re-encoded audio. 128 kbps is transparent for speech and most course audio.';

// Advanced settings.
$string['advanced'] = 'Advanced';
$string['minmediabytes'] = 'Minimum media file size (bytes)';
$string['minmediabytes_desc'] = 'Media files smaller than this are left untouched, since compressing tiny files rarely helps. Default 51200 (50 KB).';
$string['maxpackagemb'] = 'Maximum package size (MB)';
$string['maxpackagemb_desc'] = 'Skip packages larger than this to protect the server. Set to 0 for no limit.';
$string['logretentiondays'] = 'Log retention (days)';
$string['logretentiondays_desc'] = 'Delete compression log entries older than this many days. Set to 0 to keep them forever.';

// Tool paths.
$string['toolpaths'] = 'Tool paths';
$string['toolpaths_desc'] = 'Leave blank to auto-detect these tools from the system PATH. If they are installed in a non-standard location, provide their full paths here.';
$string['pathffmpeg'] = 'Path to ffmpeg';
$string['pathffmpeg_desc'] = 'Full path to the ffmpeg executable, e.g. /usr/bin/ffmpeg.';
$string['pathffprobe'] = 'Path to ffprobe';
$string['pathffprobe_desc'] = 'Full path to the ffprobe executable, e.g. /usr/bin/ffprobe.';
$string['pathmagick'] = 'Path to ImageMagick';
$string['pathmagick_desc'] = 'Full path to the ImageMagick executable (magick or convert), e.g. /usr/bin/convert.';

// Adhoc task.
$string['taskcompress'] = 'Compress a SCORM package';

// Report.
$string['report'] = 'Compression report';
$string['summary'] = 'Compressed {$a->runs} package(s), re-encoding {$a->files} media file(s). Original total: {$a->orig}; compressed total: {$a->comp}; saved {$a->saved} ({$a->percent}%).';
$string['noentries'] = 'No SCORM packages have been processed yet.';
$string['col_time'] = 'Date';
$string['col_course'] = 'Course';
$string['col_activity'] = 'Activity';
$string['col_files'] = 'Files';
$string['col_original'] = 'Original';
$string['col_compressed'] = 'Compressed';
$string['col_saved'] = 'Saved';
$string['col_status'] = 'Status';

// Statuses.
$string['status_pending'] = 'Pending';
$string['status_success'] = 'Compressed';
$string['status_skipped'] = 'Skipped';
$string['status_nogain'] = 'No size gain';
$string['status_notools'] = 'Tools unavailable';
$string['status_error'] = 'Error';

// Log messages.
$string['log_toolarge'] = 'Package exceeds the configured maximum size and was skipped.';
$string['log_notools'] = 'No compression tools are available for the enabled media types.';
$string['log_nomedia'] = 'No compressible media was found in the package.';
$string['log_extractfailed'] = 'The package could not be extracted.';
$string['log_repackfailed'] = 'The compressed package could not be rebuilt.';
$string['log_nogain'] = 'Recompression did not reduce the package size; the original was kept.';
