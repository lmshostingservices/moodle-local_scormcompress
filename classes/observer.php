<?php
// This file is part of Moodle - http://moodle.org/
namespace local_scormcompress;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer class for local_scormcompress.
 *
 * Triggered on SCORM activity creation and update.  Reads the stored
 * SCORM package ZIP, rewrites it with maximum deflate compression (level 9),
 * and replaces the Moodle-stored file — only when the recompressed file is
 * at least 5 % smaller than the original to avoid unnecessary writes.
 */
class observer {

    /**
     * Fired when any course module is created.  Filters for mod_scorm.
     */
    public static function on_module_created(\core\event\course_module_created $event): void {
        if (($event->other['modulename'] ?? '') !== 'scorm') {
            return;
        }
        self::compress_package((int) $event->objectid);
    }

    /**
     * Fired when any course module is updated.  Filters for mod_scorm.
     */
    public static function on_module_updated(\core\event\course_module_updated $event): void {
        if (($event->other['modulename'] ?? '') !== 'scorm') {
            return;
        }
        self::compress_package((int) $event->objectid);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Recompress the SCORM package stored for a given course-module ID.
     *
     * @param int $cmid  Course-module ID of the scorm activity.
     */
    private static function compress_package(int $cmid): void {
        global $DB;

        // Resolve the cm and the scorm instance.
        $cm = get_coursemodule_from_id('scorm', $cmid);
        if (!$cm) {
            return;
        }

        $scorm = $DB->get_record('scorm', ['id' => $cm->instance]);
        if (!$scorm) {
            return;
        }

        // Only handle local (uploaded) packages — not external URL packages.
        if (!empty($scorm->scormtype) && $scorm->scormtype !== SCORM_TYPE_LOCAL) {
            return;
        }

        // Retrieve the package file from Moodle file storage.
        $fs      = get_file_storage();
        $context = \context_module::instance($cmid);
        $files   = $fs->get_area_files(
            $context->id, 'mod_scorm', 'package', 0,
            'id DESC', false
        );

        if (empty($files)) {
            return;
        }

        /** @var \stored_file $original */
        $original = reset($files);

        // Only process ZIP files.
        $ext = strtolower(pathinfo($original->get_filename(), PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            return;
        }

        // Write original content to a temp file.
        $tmpdir   = make_temp_directory('local_scormcompress');
        $origpath = $tmpdir . '/' . $original->get_contenthash() . '_orig.zip';
        $comppath = $tmpdir . '/' . $original->get_contenthash() . '_comp.zip';

        try {
            $original->copy_content_to($origpath);
            $origsize = filesize($origpath);

            if ($origsize === 0) {
                return;
            }

            // Attempt recompression.
            if (!self::recompress($origpath, $comppath)) {
                return;
            }

            $newsize = filesize($comppath);

            // Only replace if at least 5 % smaller — avoids pointless writes for
            // already-optimised packages (e.g. those that contain MP4/PNG assets
            // which are incompressible).
            if ($newsize >= $origsize * 0.95) {
                return;
            }

            // Build the file-record descriptor from the original file.
            $fileinfo = [
                'contextid' => $original->get_contextid(),
                'component' => $original->get_component(),
                'filearea'  => $original->get_filearea(),
                'itemid'    => $original->get_itemid(),
                'filepath'  => $original->get_filepath(),
                'filename'  => $original->get_filename(),
            ];

            // Replace: delete the original, create the compressed copy.
            $original->delete();
            $fs->create_file_from_pathname($fileinfo, $comppath);

        } catch (\Throwable $e) {
            // Never break SCORM creation/update — log and return silently.
            debugging('local_scormcompress: ' . $e->getMessage(), DEBUG_DEVELOPER);
        } finally {
            if (file_exists($origpath)) {
                @unlink($origpath);
            }
            if (file_exists($comppath)) {
                @unlink($comppath);
            }
        }
    }

    /**
     * Rewrite a ZIP file at $src to $dst using maximum deflate compression.
     *
     * Entries that are smaller after storing (e.g. already-compressed media)
     * are stored without compression to avoid inflating them.
     *
     * @param  string $src  Source ZIP path.
     * @param  string $dst  Destination ZIP path (created/overwritten).
     * @return bool         True on success, false on any ZipArchive error.
     */
    private static function recompress(string $src, string $dst): bool {
        $in = new \ZipArchive();
        if ($in->open($src) !== true) {
            return false;
        }

        $out = new \ZipArchive();
        if ($out->open($dst, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $in->close();
            return false;
        }

        $ok = true;
        for ($i = 0; $i < $in->numFiles; $i++) {
            $stat = $in->statIndex($i);
            if ($stat === false) {
                $ok = false;
                break;
            }

            $name = $stat['name'];

            // Preserve directory entries.
            if (substr($name, -1) === '/') {
                $out->addEmptyDir($name);
                continue;
            }

            $content = $in->getFromIndex($i);
            if ($content === false) {
                $ok = false;
                break;
            }

            $out->addFromString($name, $content);

            // Set maximum deflate compression on this entry.
            // For very small files or files that are larger when deflated,
            // Deflate level 9 still wins vs. no compression; ZipArchive will
            // fall back to STORE automatically when deflation makes it larger.
            $out->setCompressionIndex(
                $out->numFiles - 1,
                \ZipArchive::CM_DEFLATE,
                9
            );
        }

        $in->close();
        $out->close();

        return $ok;
    }
}
