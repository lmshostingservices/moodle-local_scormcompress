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
 * Orchestrates compression of a SCORM package: unpack, re-encode media, repack, replace, re-parse.
 *
 * @package    local_scormcompress
 * @copyright  2026 Jamie <nct185@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_scormcompress\local;

use local_scormcompress\local\media\image_processor;
use local_scormcompress\local\media\video_processor;
use local_scormcompress\local\media\audio_processor;
use stdClass;

/**
 * Compresses the media inside a single SCORM package.
 */
class compressor {
    /** @var string Package processed successfully and replaced. */
    const STATUS_SUCCESS = 'success';
    /** @var string Nothing needed doing (already compressed, no media, or no size gain). */
    const STATUS_SKIPPED = 'skipped';
    /** @var string Re-encoding produced no overall size reduction. */
    const STATUS_NOGAIN = 'nogain';
    /** @var string Required external tools were unavailable. */
    const STATUS_NOTOOLS = 'notools';
    /** @var string An error occurred during processing. */
    const STATUS_ERROR = 'error';

    /**
     * Process the package attached to a SCORM activity.
     *
     * @param int $scormid The SCORM instance id.
     * @param int $cmid The course module id.
     * @param int $courseid The course id.
     * @return string One of the STATUS_* constants.
     */
    public function process_scorm(int $scormid, int $cmid, int $courseid): string {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/scorm/lib.php');
        require_once($CFG->dirroot . '/mod/scorm/locallib.php');

        $scorm = $DB->get_record('scorm', ['id' => $scormid]);
        if (!$scorm) {
            return self::STATUS_SKIPPED;
        }

        // Only locally uploaded ZIP packages can be recompressed.
        if ($scorm->scormtype !== SCORM_TYPE_LOCAL) {
            return self::STATUS_SKIPPED;
        }

        try {
            $context = \context_module::instance($cmid);
        } catch (\moodle_exception $e) {
            return self::STATUS_SKIPPED;
        }

        $fs = get_file_storage();
        $file = $fs->get_file($context->id, 'mod_scorm', 'package', 0, '/', $scorm->reference);
        if (!$file) {
            $files = $fs->get_area_files($context->id, 'mod_scorm', 'package', 0, '', false);
            $file = $files ? reset($files) : null;
        }
        if (!$file) {
            return self::STATUS_SKIPPED;
        }

        $filename = $file->get_filename();
        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'zip') {
            // A bare imsmanifest.xml upload has nothing to compress.
            return self::STATUS_SKIPPED;
        }

        $currenthash = $file->get_contenthash();
        $origsize = (int)$file->get_filesize();

        // Idempotency: never reprocess our own output or an already-processed source.
        $alreadydone = $DB->record_exists_select(
            'local_scormcompress_log',
            'scormid = :sid AND (resultcontenthash = :h1 OR (sourcecontenthash = :h2 AND status = :st))',
            ['sid' => $scormid, 'h1' => $currenthash, 'h2' => $currenthash, 'st' => self::STATUS_SUCCESS]
        );
        if ($alreadydone) {
            return self::STATUS_SKIPPED;
        }

        // Respect an optional maximum package size guard.
        $maxpackagemb = (int)get_config('local_scormcompress', 'maxpackagemb');
        if ($maxpackagemb > 0 && $origsize > $maxpackagemb * 1024 * 1024) {
            $this->write_log($scorm, $cmid, $courseid, $filename, $currenthash, null,
                $origsize, $origsize, 0, self::STATUS_SKIPPED,
                get_string('log_toolarge', 'local_scormcompress'));
            return self::STATUS_SKIPPED;
        }

        $settings = $this->effective_settings();

        // Bail early if no compression is even possible in this environment.
        if (!$this->any_backend_available($settings)) {
            $this->write_log($scorm, $cmid, $courseid, $filename, $currenthash, null,
                $origsize, $origsize, 0, self::STATUS_NOTOOLS,
                get_string('log_notools', 'local_scormcompress'));
            return self::STATUS_NOTOOLS;
        }

        \core_php_time_limit::raise(0);
        raise_memory_limit(MEMORY_HUGE);

        try {
            return $this->run_pipeline($scorm, $cmid, $courseid, $file, $context, $settings,
                $currenthash, $origsize);
        } catch (\Throwable $e) {
            $this->write_log($scorm, $cmid, $courseid, $filename, $currenthash, null,
                $origsize, $origsize, 0, self::STATUS_ERROR, $e->getMessage());
            debugging('local_scormcompress: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return self::STATUS_ERROR;
        }
    }

    /**
     * Extract, re-encode, repackage and replace the package, then re-parse the SCORM.
     *
     * @param stdClass $scorm The SCORM record.
     * @param int $cmid Course module id.
     * @param int $courseid Course id.
     * @param \stored_file $file The stored package file.
     * @param \context $context The module context.
     * @param array $settings Effective settings.
     * @param string $currenthash Current package content hash.
     * @param int $origsize Original package size in bytes.
     * @return string A STATUS_* constant.
     */
    protected function run_pipeline(stdClass $scorm, int $cmid, int $courseid, \stored_file $file,
            \context $context, array $settings, string $currenthash, int $origsize): string {
        global $DB;

        $filename = $file->get_filename();
        $workdir = make_request_directory();
        $sourcezip = $workdir . '/source.zip';
        $extractdir = $workdir . '/extract';
        check_dir_exists($extractdir);

        $file->copy_content_to($sourcezip);

        $packer = get_file_packer('application/zip');
        if ($packer->extract_to_pathname($sourcezip, $extractdir) === false) {
            $this->write_log($scorm, $cmid, $courseid, $filename, $currenthash, null,
                $origsize, $origsize, 0, self::STATUS_ERROR,
                get_string('log_extractfailed', 'local_scormcompress'));
            return self::STATUS_ERROR;
        }

        $filesprocessed = $this->compress_tree($extractdir, $workdir, $settings);

        if ($filesprocessed === 0) {
            $this->write_log($scorm, $cmid, $courseid, $filename, $currenthash, null,
                $origsize, $origsize, 0, self::STATUS_SKIPPED,
                get_string('log_nomedia', 'local_scormcompress'));
            return self::STATUS_SKIPPED;
        }

        $newzip = $workdir . '/compressed.zip';
        $filelist = $this->build_file_list($extractdir);
        if ($packer->archive_to_pathname($filelist, $newzip) === false || !file_exists($newzip)) {
            $this->write_log($scorm, $cmid, $courseid, $filename, $currenthash, null,
                $origsize, $origsize, $filesprocessed, self::STATUS_ERROR,
                get_string('log_repackfailed', 'local_scormcompress'));
            return self::STATUS_ERROR;
        }

        $newsize = (int)filesize($newzip);
        if ($newsize >= $origsize) {
            // The repackaged archive is not smaller; leave the original in place.
            $this->write_log($scorm, $cmid, $courseid, $filename, $currenthash, null,
                $origsize, $newsize, $filesprocessed, self::STATUS_NOGAIN,
                get_string('log_nogain', 'local_scormcompress'));
            return self::STATUS_NOGAIN;
        }

        // Optionally retain a safety copy of the original package.
        if (get_config('local_scormcompress', 'keepbackup')) {
            $this->store_backup($context, $scorm->id, $filename, $sourcezip);
        }

        // Replace the package file, keeping the same area, item and file name.
        $fs = get_file_storage();
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'mod_scorm',
            'filearea'  => 'package',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => $filename,
        ];
        $file->delete();
        $newfile = $fs->create_file_from_pathname($filerecord, $newzip);
        $newhash = $newfile->get_contenthash();

        // Re-parse so SCORM re-extracts the new content and bumps its revision itself.
        $fresh = $DB->get_record('scorm', ['id' => $scorm->id]);
        if ($fresh) {
            $fresh->cmid = $cmid;
            scorm_parse($fresh, false);
        }

        $this->write_log($scorm, $cmid, $courseid, $filename, $currenthash, $newhash,
            $origsize, $newsize, $filesprocessed, self::STATUS_SUCCESS, null);

        $this->prune_old_logs();

        return self::STATUS_SUCCESS;
    }

    /**
     * Walk the extracted tree and compress each supported media file in place.
     *
     * @param string $extractdir The directory holding the extracted package.
     * @param string $workdir A scratch directory for temporary output.
     * @param array $settings Effective settings.
     * @return int Number of files that were replaced with a smaller version.
     */
    protected function compress_tree(string $extractdir, string $workdir, array $settings): int {
        $minbytes = max(0, (int)$settings['minmediabytes']);
        $tmpindex = 0;
        $processed = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractdir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }
            $path = $item->getPathname();
            $size = $item->getSize();
            if ($size < $minbytes) {
                continue;
            }

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            [$type, $enabled] = $this->classify($ext, $settings);
            if ($type === null || !$enabled) {
                continue;
            }

            $tmpout = $workdir . '/out_' . ($tmpindex++) . '.' . $ext;

            try {
                $ok = false;
                if ($type === 'image') {
                    $ok = image_processor::compress($path, $tmpout, $ext, $settings);
                } else if ($type === 'video') {
                    $ok = video_processor::compress($path, $tmpout, $ext, $settings);
                } else if ($type === 'audio') {
                    $ok = audio_processor::compress($path, $tmpout, $ext, $settings);
                }
            } catch (\Throwable $e) {
                $ok = false;
                debugging('local_scormcompress: failed on ' . $path . ': ' . $e->getMessage(),
                    DEBUG_DEVELOPER);
            }

            if ($ok && file_exists($tmpout) && filesize($tmpout) > 0 && filesize($tmpout) < $size) {
                // Overwrite the original media file, preserving its name and location.
                if (@copy($tmpout, $path)) {
                    $processed++;
                }
            }
            if (file_exists($tmpout)) {
                @unlink($tmpout);
            }
        }

        return $processed;
    }

    /**
     * Determine the media type and whether that type is enabled.
     *
     * @param string $ext Lower-case extension.
     * @param array $settings Effective settings.
     * @return array [string|null $type, bool $enabled]
     */
    protected function classify(string $ext, array $settings): array {
        if (in_array($ext, image_processor::supported_extensions(), true)) {
            return ['image', !empty($settings['image_enabled'])];
        }
        if (in_array($ext, video_processor::supported_extensions(), true)) {
            return ['video', !empty($settings['video_enabled'])];
        }
        if (in_array($ext, audio_processor::supported_extensions(), true)) {
            return ['audio', !empty($settings['audio_enabled'])];
        }
        return [null, false];
    }

    /**
     * Build the file list (archive path => real path) for repackaging.
     *
     * @param string $extractdir The extracted package directory.
     * @return array
     */
    protected function build_file_list(string $extractdir): array {
        $list = [];
        $prefixlen = strlen($extractdir) + 1;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractdir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }
            $full = $item->getPathname();
            $relative = str_replace('\\', '/', substr($full, $prefixlen));
            $list[$relative] = $full;
        }

        return $list;
    }

    /**
     * Store a safety copy of the original package in the plugin file area.
     *
     * @param \context $context The module context.
     * @param int $scormid The SCORM instance id.
     * @param string $filename The package file name.
     * @param string $sourcezip Path to the original package on disk.
     */
    protected function store_backup(\context $context, int $scormid, string $filename, string $sourcezip): void {
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'local_scormcompress', 'backup', $scormid);
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'local_scormcompress',
            'filearea'  => 'backup',
            'itemid'    => $scormid,
            'filepath'  => '/',
            'filename'  => $filename,
        ];
        $fs->create_file_from_pathname($filerecord, $sourcezip);
    }

    /**
     * Whether at least one compression backend is usable in this environment.
     *
     * @param array $settings Effective settings.
     * @return bool
     */
    protected function any_backend_available(array $settings): bool {
        $ffmpeg = binaries::locate('ffmpeg') !== null;
        $magick = binaries::locate('magick') !== null;
        $gd = function_exists('imagecreatetruecolor');

        $imageok = !empty($settings['image_enabled']) && ($magick || $gd);
        $videook = !empty($settings['video_enabled']) && $ffmpeg;
        $audiook = !empty($settings['audio_enabled']) && $ffmpeg;

        return $imageok || $videook || $audiook;
    }

    /**
     * Read the effective settings for a compression run.
     *
     * @return array
     */
    protected function effective_settings(): array {
        $defaults = [
            'image_enabled'     => 1,
            'image_quality'     => 82,
            'image_maxdimension' => 1600,
            'video_enabled'     => 1,
            'video_crf'         => 23,
            'video_maxheight'   => 720,
            'video_preset'      => 'medium',
            'audio_enabled'     => 1,
            'audio_bitrate'     => 128,
            'minmediabytes'     => 51200,
        ];

        $settings = [];
        foreach ($defaults as $key => $default) {
            $value = get_config('local_scormcompress', $key);
            $settings[$key] = ($value === false || $value === null || $value === '') ? $default : $value;
        }
        return $settings;
    }

    /**
     * Insert a log record describing a compression attempt.
     *
     * @param stdClass $scorm The SCORM record.
     * @param int $cmid Course module id.
     * @param int $courseid Course id.
     * @param string $filename Package file name.
     * @param string|null $sourcehash Source content hash.
     * @param string|null $resulthash Result content hash.
     * @param int $origsize Original size in bytes.
     * @param int $newsize New size in bytes.
     * @param int $filesprocessed Number of media files re-encoded.
     * @param string $status A STATUS_* constant.
     * @param string|null $message Optional detail message.
     */
    protected function write_log(stdClass $scorm, int $cmid, int $courseid, string $filename,
            ?string $sourcehash, ?string $resulthash, int $origsize, int $newsize,
            int $filesprocessed, string $status, ?string $message): void {
        global $DB;

        $record = new stdClass();
        $record->scormid = $scorm->id;
        $record->cmid = $cmid;
        $record->courseid = $courseid;
        $record->filename = $filename;
        $record->sourcecontenthash = $sourcehash;
        $record->resultcontenthash = $resulthash;
        $record->originalsize = $origsize;
        $record->compressedsize = $newsize;
        $record->filesprocessed = $filesprocessed;
        $record->status = $status;
        $record->message = $message;
        $record->timecreated = time();

        $DB->insert_record('local_scormcompress_log', $record);
    }

    /**
     * Remove log records older than the configured retention period.
     */
    protected function prune_old_logs(): void {
        global $DB;

        $days = (int)get_config('local_scormcompress', 'logretentiondays');
        if ($days <= 0) {
            return;
        }
        $cutoff = time() - ($days * DAYSECS);
        $DB->delete_records_select('local_scormcompress_log', 'timecreated < :cutoff', ['cutoff' => $cutoff]);
    }
}
