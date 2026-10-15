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
 * Event observers that queue SCORM package compression.
 *
 * @package    local_scormcompress
 * @copyright  2026 Jamie <nct185@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_scormcompress;

use core\event\course_module_created;
use core\event\course_module_updated;
use local_scormcompress\task\compress_scorm_task;

/**
 * Observes course module create/update events and schedules compression.
 */
class observer {
    /**
     * Handle a newly created course module.
     *
     * @param course_module_created $event The triggered event.
     */
    public static function course_module_created(course_module_created $event): void {
        self::maybe_queue($event->other['modulename'] ?? '',
            (int)($event->other['instanceid'] ?? 0),
            (int)$event->objectid,
            (int)$event->courseid,
            true);
    }

    /**
     * Handle an updated course module.
     *
     * @param course_module_updated $event The triggered event.
     */
    public static function course_module_updated(course_module_updated $event): void {
        self::maybe_queue($event->other['modulename'] ?? '',
            (int)($event->other['instanceid'] ?? 0),
            (int)$event->objectid,
            (int)$event->courseid,
            false);
    }

    /**
     * Queue a compression task for a SCORM module when the plugin is configured to do so.
     *
     * @param string $modulename The module name from the event.
     * @param int $scormid The SCORM instance id.
     * @param int $cmid The course module id.
     * @param int $courseid The course id.
     * @param bool $iscreate Whether this came from a create event.
     */
    protected static function maybe_queue(string $modulename, int $scormid, int $cmid,
            int $courseid, bool $iscreate): void {
        if ($modulename !== 'scorm' || $scormid <= 0 || $cmid <= 0) {
            return;
        }

        // Treat an unset value as the default (enabled).
        // Setting defaults are not always written to config until first saved.
        $enabled = get_config('local_scormcompress', 'enabled');
        if ($enabled !== false && !$enabled) {
            return;
        }

        // When only compressing on creation, ignore later setting edits.
        $onupdate = get_config('local_scormcompress', 'processonupdate');
        if (!$iscreate && $onupdate !== false && !$onupdate) {
            return;
        }

        $task = new compress_scorm_task();
        $task->set_custom_data([
            'scormid'  => $scormid,
            'cmid'     => $cmid,
            'courseid' => $courseid,
        ]);
        $task->set_component('local_scormcompress');

        // The second argument dedupes identical pending tasks queued before cron runs.
        \core\task\manager::queue_adhoc_task($task, true);
    }
}
