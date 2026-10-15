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
 * Video compression using ffmpeg.
 *
 * @package    local_scormcompress
 * @copyright  2026 Jamie <nct185@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_scormcompress\local\media;

use local_scormcompress\local\binaries;

/**
 * Re-encodes video files at a target quality, capping the resolution.
 */
class video_processor {
    /**
     * Extensions handled by this processor.
     *
     * @return array
     */
    public static function supported_extensions(): array {
        return ['mp4', 'm4v', 'mov', 'webm', 'avi', 'mkv', 'flv', 'wmv'];
    }

    /**
     * Compress a video to a new file, preserving the container extension.
     *
     * @param string $inpath Source video path.
     * @param string $outpath Destination path (same extension as the source).
     * @param string $ext Lower-case file extension.
     * @param array $settings Effective settings (video_crf, video_maxheight, video_preset, audio_bitrate).
     * @return bool True if a compressed file was written.
     */
    public static function compress(string $inpath, string $outpath, string $ext, array $settings): bool {
        $ffmpeg = binaries::locate('ffmpeg');
        if ($ffmpeg === null) {
            return false;
        }

        $crf = max(0, min(51, (int)$settings['video_crf']));
        $maxheight = max(0, (int)$settings['video_maxheight']);
        $preset = (string)$settings['video_preset'];
        $audiobitrate = max(8, (int)$settings['audio_bitrate']);

        $args = [$ffmpeg, '-y', '-nostdin', '-loglevel', 'error', '-i', $inpath];

        // Only downscale when the source is taller than the cap; keep width even (-2).
        if ($maxheight > 0) {
            $args[] = '-vf';
            $args[] = 'scale=-2:min(' . $maxheight . '\\,ih)';
        }

        if ($ext === 'webm') {
            // WebM must carry VP9 video and Opus audio.
            $args = array_merge($args, [
                '-c:v', 'libvpx-vp9',
                '-crf', (string)($crf + 8),
                '-b:v', '0',
                '-row-mt', '1',
                '-c:a', 'libopus',
                '-b:a', $audiobitrate . 'k',
            ]);
        } else {
            $args = array_merge($args, [
                '-c:v', 'libx264',
                '-preset', $preset,
                '-crf', (string)$crf,
                '-pix_fmt', 'yuv420p',
                '-c:a', 'aac',
                '-b:a', $audiobitrate . 'k',
            ]);
            if (in_array($ext, ['mp4', 'm4v', 'mov'], true)) {
                $args[] = '-movflags';
                $args[] = '+faststart';
            }
        }

        $args[] = $outpath;

        // Videos can take a long time; allow up to one hour per file.
        [$code] = binaries::run($args, 3600);
        return $code === 0 && file_exists($outpath) && filesize($outpath) > 0;
    }
}
