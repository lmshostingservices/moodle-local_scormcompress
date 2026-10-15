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
 * Image compression using ImageMagick with a GD fallback.
 *
 * @package    local_scormcompress
 * @copyright  2026 Jamie <nct185@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_scormcompress\local\media;

use local_scormcompress\local\binaries;

/**
 * Re-encodes raster images at a target quality and maximum dimension.
 */
class image_processor {
    /**
     * Extensions handled by this processor. Animated GIFs are deliberately excluded.
     *
     * @return array
     */
    public static function supported_extensions(): array {
        return ['jpg', 'jpeg', 'png', 'webp'];
    }

    /**
     * Compress an image to a new file.
     *
     * @param string $inpath Source image path.
     * @param string $outpath Destination path (same extension as the source).
     * @param string $ext Lower-case file extension.
     * @param array $settings Effective settings (image_quality, image_maxdimension).
     * @return bool True if a compressed file was written to $outpath.
     */
    public static function compress(string $inpath, string $outpath, string $ext, array $settings): bool {
        $quality = max(1, min(100, (int)$settings['image_quality']));
        $maxdim = max(0, (int)$settings['image_maxdimension']);

        $magick = binaries::locate('magick');
        if ($magick !== null) {
            return self::compress_with_magick($magick, $inpath, $outpath, $quality, $maxdim);
        }
        return self::compress_with_gd($inpath, $outpath, $ext, $quality, $maxdim);
    }

    /**
     * Compress using the ImageMagick command line.
     *
     * @param string $magick Resolved ImageMagick command.
     * @param string $inpath Source path.
     * @param string $outpath Destination path.
     * @param int $quality Target quality.
     * @param int $maxdim Maximum width/height in pixels (0 = no limit).
     * @return bool
     */
    protected static function compress_with_magick(string $magick, string $inpath, string $outpath,
            int $quality, int $maxdim): bool {
        $args = [$magick, $inpath, '-auto-orient', '-strip'];
        if ($maxdim > 0) {
            // The trailing ">" only shrinks images larger than the geometry.
            $args[] = '-resize';
            $args[] = $maxdim . 'x' . $maxdim . '>';
        }
        $args[] = '-quality';
        $args[] = (string)$quality;
        $args[] = $outpath;

        [$code] = binaries::run($args, 300);
        return $code === 0 && file_exists($outpath) && filesize($outpath) > 0;
    }

    /**
     * Compress using PHP GD as a fallback when ImageMagick is unavailable.
     *
     * @param string $inpath Source path.
     * @param string $outpath Destination path.
     * @param string $ext Lower-case extension.
     * @param int $quality Target quality.
     * @param int $maxdim Maximum width/height in pixels (0 = no limit).
     * @return bool
     */
    protected static function compress_with_gd(string $inpath, string $outpath, string $ext,
            int $quality, int $maxdim): bool {
        if (!function_exists('imagecreatetruecolor')) {
            return false;
        }

        $info = @getimagesize($inpath);
        if ($info === false) {
            return false;
        }

        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $src = @imagecreatefromjpeg($inpath);
                break;
            case 'png':
                $src = @imagecreatefrompng($inpath);
                break;
            case 'webp':
                $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($inpath) : false;
                break;
            default:
                return false;
        }

        if (!$src) {
            return false;
        }

        $width = imagesx($src);
        $height = imagesy($src);
        $dst = $src;
        $scaled = false;

        if ($maxdim > 0 && ($width > $maxdim || $height > $maxdim)) {
            $ratio = min($maxdim / $width, $maxdim / $height);
            $newwidth = max(1, (int)round($width * $ratio));
            $newheight = max(1, (int)round($height * $ratio));
            $dst = imagecreatetruecolor($newwidth, $newheight);
            if ($ext === 'png' || $ext === 'webp') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
                imagefilledrectangle($dst, 0, 0, $newwidth, $newheight, $transparent);
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
            $scaled = true;
        }

        $ok = false;
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $ok = imagejpeg($dst, $outpath, $quality);
                break;
            case 'png':
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                // Map quality (1-100) to PNG compression level (0-9).
                $level = 9 - (int)round(($quality / 100) * 9);
                $level = max(0, min(9, $level));
                $ok = imagepng($dst, $outpath, $level);
                break;
            case 'webp':
                $ok = function_exists('imagewebp') ? imagewebp($dst, $outpath, $quality) : false;
                break;
        }

        if ($scaled) {
            imagedestroy($dst);
        }
        imagedestroy($src);

        return $ok && file_exists($outpath) && filesize($outpath) > 0;
    }
}
