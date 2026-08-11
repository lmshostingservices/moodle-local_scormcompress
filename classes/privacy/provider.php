<?php
// This file is part of Moodle - http://moodle.org/
namespace local_scormcompress\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for local_scormcompress.
 *
 * This plugin stores no personal data — it only rewrites existing SCORM
 * package ZIP files that Moodle has already stored.
 */
class provider implements \core_privacy\local\metadata\null_provider {
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
