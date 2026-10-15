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
 * Admin settings and report page registration for local_scormcompress.
 *
 * @package    local_scormcompress
 * @copyright  2026 Jamie <nct185@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $category = new admin_category('local_scormcompress_cat', new lang_string('pluginname', 'local_scormcompress'));
    $ADMIN->add('localplugins', $category);

    $settings = new admin_settingpage('local_scormcompress', new lang_string('settings', 'local_scormcompress'));

    if ($ADMIN->fulltree) {

        // Master switch.
        $settings->add(new admin_setting_heading(
            'local_scormcompress/generalheading',
            new lang_string('general', 'local_scormcompress'),
            new lang_string('general_desc', 'local_scormcompress')
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_scormcompress/enabled',
            new lang_string('enabled', 'local_scormcompress'),
            new lang_string('enabled_desc', 'local_scormcompress'),
            1
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_scormcompress/processonupdate',
            new lang_string('processonupdate', 'local_scormcompress'),
            new lang_string('processonupdate_desc', 'local_scormcompress'),
            1
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_scormcompress/keepbackup',
            new lang_string('keepbackup', 'local_scormcompress'),
            new lang_string('keepbackup_desc', 'local_scormcompress'),
            0
        ));

        // Detected external tools status.
        $settings->add(new admin_setting_heading(
            'local_scormcompress/toolstatusheading',
            new lang_string('toolstatus', 'local_scormcompress'),
            \local_scormcompress\local\binaries::status_html()
        ));

        // Image compression.
        $settings->add(new admin_setting_heading(
            'local_scormcompress/imageheading',
            new lang_string('images', 'local_scormcompress'),
            ''
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_scormcompress/image_enabled',
            new lang_string('image_enabled', 'local_scormcompress'),
            new lang_string('image_enabled_desc', 'local_scormcompress'),
            1
        ));

        $settings->add(new admin_setting_configtext(
            'local_scormcompress/image_quality',
            new lang_string('image_quality', 'local_scormcompress'),
            new lang_string('image_quality_desc', 'local_scormcompress'),
            82,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            'local_scormcompress/image_maxdimension',
            new lang_string('image_maxdimension', 'local_scormcompress'),
            new lang_string('image_maxdimension_desc', 'local_scormcompress'),
            1600,
            PARAM_INT
        ));

        // Video compression.
        $settings->add(new admin_setting_heading(
            'local_scormcompress/videoheading',
            new lang_string('videos', 'local_scormcompress'),
            ''
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_scormcompress/video_enabled',
            new lang_string('video_enabled', 'local_scormcompress'),
            new lang_string('video_enabled_desc', 'local_scormcompress'),
            1
        ));

        $settings->add(new admin_setting_configtext(
            'local_scormcompress/video_crf',
            new lang_string('video_crf', 'local_scormcompress'),
            new lang_string('video_crf_desc', 'local_scormcompress'),
            23,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            'local_scormcompress/video_maxheight',
            new lang_string('video_maxheight', 'local_scormcompress'),
            new lang_string('video_maxheight_desc', 'local_scormcompress'),
            720,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configselect(
            'local_scormcompress/video_preset',
            new lang_string('video_preset', 'local_scormcompress'),
            new lang_string('video_preset_desc', 'local_scormcompress'),
            'medium',
            [
                'ultrafast' => 'ultrafast',
                'superfast' => 'superfast',
                'veryfast'  => 'veryfast',
                'faster'    => 'faster',
                'fast'      => 'fast',
                'medium'    => 'medium',
                'slow'      => 'slow',
                'slower'    => 'slower',
                'veryslow'  => 'veryslow',
            ]
        ));

        // Audio compression.
        $settings->add(new admin_setting_heading(
            'local_scormcompress/audioheading',
            new lang_string('audio', 'local_scormcompress'),
            ''
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_scormcompress/audio_enabled',
            new lang_string('audio_enabled', 'local_scormcompress'),
            new lang_string('audio_enabled_desc', 'local_scormcompress'),
            1
        ));

        $settings->add(new admin_setting_configtext(
            'local_scormcompress/audio_bitrate',
            new lang_string('audio_bitrate', 'local_scormcompress'),
            new lang_string('audio_bitrate_desc', 'local_scormcompress'),
            128,
            PARAM_INT
        ));

        // Advanced / limits.
        $settings->add(new admin_setting_heading(
            'local_scormcompress/advancedheading',
            new lang_string('advanced', 'local_scormcompress'),
            ''
        ));

        $settings->add(new admin_setting_configtext(
            'local_scormcompress/minmediabytes',
            new lang_string('minmediabytes', 'local_scormcompress'),
            new lang_string('minmediabytes_desc', 'local_scormcompress'),
            51200,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            'local_scormcompress/maxpackagemb',
            new lang_string('maxpackagemb', 'local_scormcompress'),
            new lang_string('maxpackagemb_desc', 'local_scormcompress'),
            0,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            'local_scormcompress/logretentiondays',
            new lang_string('logretentiondays', 'local_scormcompress'),
            new lang_string('logretentiondays_desc', 'local_scormcompress'),
            365,
            PARAM_INT
        ));

        // Explicit binary paths (leave blank to auto-detect from PATH).
        $settings->add(new admin_setting_heading(
            'local_scormcompress/toolsheading',
            new lang_string('toolpaths', 'local_scormcompress'),
            new lang_string('toolpaths_desc', 'local_scormcompress')
        ));

        $settings->add(new admin_setting_configexecutable(
            'local_scormcompress/pathffmpeg',
            new lang_string('pathffmpeg', 'local_scormcompress'),
            new lang_string('pathffmpeg_desc', 'local_scormcompress'),
            ''
        ));

        $settings->add(new admin_setting_configexecutable(
            'local_scormcompress/pathffprobe',
            new lang_string('pathffprobe', 'local_scormcompress'),
            new lang_string('pathffprobe_desc', 'local_scormcompress'),
            ''
        ));

        $settings->add(new admin_setting_configexecutable(
            'local_scormcompress/pathmagick',
            new lang_string('pathmagick', 'local_scormcompress'),
            new lang_string('pathmagick_desc', 'local_scormcompress'),
            ''
        ));
    }

    $ADMIN->add('local_scormcompress_cat', $settings);

    $ADMIN->add('local_scormcompress_cat', new admin_externalpage(
        'local_scormcompress_report',
        new lang_string('report', 'local_scormcompress'),
        new moodle_url('/local/scormcompress/report.php'),
        'local/scormcompress:viewreport'
    ));

    // Prevent Moodle adding a duplicate default settings link under the plugin.
    $settings = null;
}
