<?php
// This file is part of Moodle - http://moodle.org/
defined('MOODLE_INTERNAL') || die();

/**
 * Event observers for local_scormcompress.
 *
 * Listens for SCORM course module creation and updates so that newly
 * uploaded local SCORM packages are automatically recompressed at the
 * highest deflate level, reducing stored file size with no admin action.
 */
$observers = [
    [
        'eventname' => '\core\event\course_module_created',
        'callback'  => '\local_scormcompress\observer::on_module_created',
        'priority'  => 0,
        'internal'  => false,
    ],
    [
        'eventname' => '\core\event\course_module_updated',
        'callback'  => '\local_scormcompress\observer::on_module_updated',
        'priority'  => 0,
        'internal'  => false,
    ],
];
