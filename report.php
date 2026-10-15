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
 * Compression activity report for local_scormcompress.
 *
 * @package    local_scormcompress
 * @copyright  2026 Jamie <nct185@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_once($CFG->libdir . '/adminlib.php');

require_login();
admin_externalpage_setup('local_scormcompress_report');
require_capability('local/scormcompress:viewreport', context_system::instance());

$page = optional_param('page', 0, PARAM_INT);
$perpage = 50;

$totalcount = $DB->count_records('local_scormcompress_log');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report', 'local_scormcompress'));

// Aggregate savings across successful runs.
$totals = $DB->get_record_sql(
    "SELECT COUNT(*) AS runs,
            COALESCE(SUM(originalsize), 0) AS orig,
            COALESCE(SUM(compressedsize), 0) AS comp,
            COALESCE(SUM(filesprocessed), 0) AS files
       FROM {local_scormcompress_log}
      WHERE status = :status",
    ['status' => \local_scormcompress\local\compressor::STATUS_SUCCESS]
);

$saved = max(0, (int)$totals->orig - (int)$totals->comp);
$percent = ((int)$totals->orig > 0) ? round(($saved / (int)$totals->orig) * 100, 1) : 0;

$summary = get_string('summary', 'local_scormcompress', (object)[
    'runs'    => (int)$totals->runs,
    'files'   => (int)$totals->files,
    'orig'    => display_size((int)$totals->orig),
    'comp'    => display_size((int)$totals->comp),
    'saved'   => display_size($saved),
    'percent' => $percent,
]);
echo $OUTPUT->box($summary, 'generalbox');

if ($totalcount == 0) {
    echo $OUTPUT->notification(get_string('noentries', 'local_scormcompress'), 'info');
    echo $OUTPUT->footer();
    die();
}

$sql = "SELECT l.*, c.fullname AS coursename, s.name AS scormname
          FROM {local_scormcompress_log} l
     LEFT JOIN {course} c ON c.id = l.courseid
     LEFT JOIN {scorm} s ON s.id = l.scormid
      ORDER BY l.timecreated DESC";
$records = $DB->get_records_sql($sql, [], $page * $perpage, $perpage);

$table = new html_table();
$table->head = [
    get_string('col_time', 'local_scormcompress'),
    get_string('col_course', 'local_scormcompress'),
    get_string('col_activity', 'local_scormcompress'),
    get_string('col_files', 'local_scormcompress'),
    get_string('col_original', 'local_scormcompress'),
    get_string('col_compressed', 'local_scormcompress'),
    get_string('col_saved', 'local_scormcompress'),
    get_string('col_status', 'local_scormcompress'),
];
$table->attributes['class'] = 'generaltable';

foreach ($records as $row) {
    $coursecell = '-';
    if ($row->courseid && $row->coursename !== null) {
        $courseurl = new moodle_url('/course/view.php', ['id' => $row->courseid]);
        $coursecell = html_writer::link($courseurl, format_string($row->coursename));
    }

    $activitycell = $row->scormname !== null ? format_string($row->scormname) : s($row->filename);
    if ($row->cmid) {
        $cmurl = new moodle_url('/mod/scorm/view.php', ['id' => $row->cmid]);
        $activitycell = html_writer::link($cmurl, $activitycell);
    }

    $rowsaved = max(0, (int)$row->originalsize - (int)$row->compressedsize);
    $rowpercent = ((int)$row->originalsize > 0)
        ? round(($rowsaved / (int)$row->originalsize) * 100, 1) : 0;

    $statuslabel = get_string('status_' . $row->status, 'local_scormcompress');
    if ($row->status !== \local_scormcompress\local\compressor::STATUS_SUCCESS && !empty($row->message)) {
        $statuslabel .= html_writer::empty_tag('br') .
            html_writer::tag('small', s($row->message), ['class' => 'text-muted']);
    }

    $table->data[] = [
        userdate($row->timecreated, get_string('strftimedatetimeshort', 'core_langconfig')),
        $coursecell,
        $activitycell,
        (int)$row->filesprocessed,
        display_size((int)$row->originalsize),
        display_size((int)$row->compressedsize),
        ($row->status === \local_scormcompress\local\compressor::STATUS_SUCCESS)
            ? display_size($rowsaved) . ' (' . $rowpercent . '%)' : '-',
        $statuslabel,
    ];
}

echo html_writer::table($table);
echo $OUTPUT->paging_bar($totalcount, $page, $perpage, new moodle_url('/local/scormcompress/report.php'));
echo $OUTPUT->footer();
