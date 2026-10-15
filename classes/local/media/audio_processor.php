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
 * Audio compression using ffmpeg.
 *
 * @package    local_scormcompress
 * @copyright  2026 Jamie <nct185@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_scormcompress\local\media;

use local_scormcompress\local\binaries;

/**
 * Re-encodes audio files at a target bitrate, preserving the container extension.
 */
class audio_processor {
    /**
     * Extensions handled by this processor. WAV/FLAC are skipped to avoid changing references.
     *
     * @return array
     */
    public static function supported_extensions(): array {
        return ['mp3', 'm4a', 'aac', 'ogg', 'oga', 'opus'];
    }

    /**
     * Compress an audio file to a new file.
     *
     * @param string $inpath Source audio path.
     * @param string $outpath Destination path (same extension as the source).
     * @param string $ext Lower-case file extension.
     * @param array $settings Effective settings (audio_bitrate).
     * @return bool True if a compressed file was written.
     */
    public static function compress(string $inpath, string $outpath, string $ext, array $settings): bool {
        $ffmpeg = binaries::locate('ffmpeg');
        if ($ffmpeg === null) {
            return false;
        }

        $bitrate = max(8, (int)$settings['audio_bitrate']);
        $args = [$ffmpeg, '-y', '-nostdin', '-loglevel', 'error', '-vn', '-i', $inpath];

        switch ($ext) {
            case 'mp3':
                $args = array_merge($args, ['-c:a', 'libmp3lame', '-b:a', $bitrate . 'k']);
                break;
            case 'm4a':
            case 'aac':
                $args = array_merge($args, ['-c:a', 'aac', '-b:a', $bitrate . 'k']);
                break;
            case 'ogg':
            case 'oga':
                $args = array_merge($args, ['-c:a', 'libvorbis', '-b:a', $bitrate . 'k']);
                break;
            case 'opus':
                $args = array_merge($args, ['-c:a', 'libopus', '-b:a', min($bitrate, 96) . 'k']);
                break;
            default:
                return false;
        }

        $args[] = $outpath;

        [$code] = binaries::run($args, 900);
        return $code === 0 && file_exists($outpath) && filesize($outpath) > 0;
    }
}
