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
 * Version metadata for the SCORM Compress local plugin.
 *
 * @package    local_scormcompress
 * @copyright  2026 Jamie <nct185@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_scormcompress';
$plugin->version   = 2026101501;          // YYYYMMDDXX - bump on every release.
$plugin->requires  = 2022112800;          // Moodle 4.1 LTS minimum.
$plugin->supported = [401, 500];          // Supported from branch 4.1 to 5.0.
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.2';
