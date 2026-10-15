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
 * Unit tests for local_scormcompress.
 *
 * @package    local_scormcompress
 * @copyright  2026 Jamie <nct185@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_scormcompress;

use local_scormcompress\local\compressor;
use local_scormcompress\local\media\image_processor;

/**
 * Test the media classification and image compression behaviour.
 *
 * @covers \local_scormcompress\local\compressor
 * @covers \local_scormcompress\local\media\image_processor
 */
final class compressor_test extends \advanced_testcase {
    /**
     * Media type classification maps extensions to the right processor.
     */
    public function test_classify_extensions(): void {
        $this->resetAfterTest(true);

        $compressor = new compressor();
        $method = new \ReflectionMethod($compressor, 'classify');
        $method->setAccessible(true);

        $settings = [
            'image_enabled' => 1,
            'video_enabled' => 1,
            'audio_enabled' => 0,
        ];

        $this->assertSame(['image', true], $method->invoke($compressor, 'jpg', $settings));
        $this->assertSame(['image', true], $method->invoke($compressor, 'png', $settings));
        $this->assertSame(['video', true], $method->invoke($compressor, 'mp4', $settings));
        // Audio is disabled in these settings, so enabled flag must be false.
        $this->assertSame(['audio', false], $method->invoke($compressor, 'mp3', $settings));
        // Unknown/left-alone types return null.
        $this->assertSame([null, false], $method->invoke($compressor, 'html', $settings));
        $this->assertSame([null, false], $method->invoke($compressor, 'gif', $settings));
    }

    /**
     * Effective settings fall back to the documented defaults when unset.
     */
    public function test_effective_settings_defaults(): void {
        $this->resetAfterTest(true);

        $compressor = new compressor();
        $method = new \ReflectionMethod($compressor, 'effective_settings');
        $method->setAccessible(true);
        $settings = $method->invoke($compressor);

        $this->assertEquals(82, $settings['image_quality']);
        $this->assertEquals(1600, $settings['image_maxdimension']);
        $this->assertEquals(23, $settings['video_crf']);
        $this->assertEquals(720, $settings['video_maxheight']);
        $this->assertEquals('medium', $settings['video_preset']);
        $this->assertEquals(128, $settings['audio_bitrate']);
    }

    /**
     * A large JPEG is re-encoded to a smaller, still-valid image.
     */
    public function test_image_compression_reduces_size(): void {
        $this->resetAfterTest(true);

        if (!function_exists('imagecreatetruecolor') && \local_scormcompress\local\binaries::locate('magick') === null) {
            $this->markTestSkipped('Neither GD nor ImageMagick is available.');
        }

        $workdir = make_request_directory();
        $source = $workdir . '/source.jpg';
        $dest = $workdir . '/dest.jpg';

        // Build a noisy 2000x2000 image so there is plenty to compress.
        $img = imagecreatetruecolor(2000, 2000);
        for ($i = 0; $i < 6000; $i++) {
            $colour = imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255));
            imagefilledrectangle($img,
                random_int(0, 1999), random_int(0, 1999),
                random_int(0, 1999), random_int(0, 1999), $colour);
        }
        imagejpeg($img, $source, 100);
        imagedestroy($img);

        $settings = ['image_quality' => 70, 'image_maxdimension' => 1200];
        $ok = image_processor::compress($source, $dest, 'jpg', $settings);

        $this->assertTrue($ok);
        $this->assertFileExists($dest);
        $this->assertLessThan(filesize($source), filesize($dest));

        // The output must still be a valid, downscaled image.
        $info = getimagesize($dest);
        $this->assertNotFalse($info);
        $this->assertLessThanOrEqual(1200, $info[0]);
        $this->assertLessThanOrEqual(1200, $info[1]);
    }
}
