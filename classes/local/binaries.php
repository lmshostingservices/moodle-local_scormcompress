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
 * Detection and execution of external media tools (ffmpeg, ffprobe, ImageMagick).
 *
 * @package    local_scormcompress
 * @copyright  2026 Jamie <nct185@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_scormcompress\local;

/**
 * Locates and runs the command line tools used for compression.
 *
 * Commands are executed with proc_open() using the array argument form so that
 * no arguments are ever passed through a shell, avoiding shell-injection risks.
 */
class binaries {
    /** @var array Static per-request cache of resolved tool paths. */
    protected static $resolved = [];

    /**
     * Whether the PHP environment allows launching external processes.
     *
     * @return bool
     */
    public static function can_run(): bool {
        if (!function_exists('proc_open')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        return !in_array('proc_open', $disabled, true);
    }

    /**
     * Resolve the absolute path (or bare command) for a named tool.
     *
     * @param string $tool One of 'ffmpeg', 'ffprobe' or 'magick'.
     * @return string|null The usable command, or null if not found.
     */
    public static function locate(string $tool): ?string {
        if (array_key_exists($tool, self::$resolved)) {
            return self::$resolved[$tool];
        }

        $result = null;

        if (self::can_run()) {
            $configured = trim((string)get_config('local_scormcompress', 'path' . $tool));
            $candidates = $configured !== '' ? [$configured] : self::default_candidates($tool);

            foreach ($candidates as $candidate) {
                if (self::probe($candidate)) {
                    $result = $candidate;
                    break;
                }
            }
        }

        self::$resolved[$tool] = $result;
        return $result;
    }

    /**
     * Default command candidates to try when no explicit path is configured.
     *
     * @param string $tool The tool name.
     * @return array
     */
    protected static function default_candidates(string $tool): array {
        switch ($tool) {
            case 'ffmpeg':
                return ['ffmpeg'];
            case 'ffprobe':
                return ['ffprobe'];
            case 'magick':
                // ImageMagick 7 ships "magick"; ImageMagick 6 ships "convert".
                return ['magick', 'convert'];
            default:
                return [];
        }
    }

    /**
     * Check that a candidate command responds to a version query.
     *
     * @param string $command The command to test.
     * @return bool
     */
    protected static function probe(string $command): bool {
        [$code] = self::run([$command, '-version'], 20);
        return $code === 0;
    }

    /**
     * Return the first line of a tool's version output, for display.
     *
     * @param string $command The resolved command.
     * @return string
     */
    public static function version_line(string $command): string {
        [$code, $out] = self::run([$command, '-version'], 20);
        if ($code !== 0) {
            return '';
        }
        $lines = preg_split('/\r?\n/', trim($out));
        return $lines[0] ?? '';
    }

    /**
     * Run a command with an argument array (no shell involved).
     *
     * @param array $cmd The command and its arguments.
     * @param int $timeout Maximum seconds to allow before the process is killed.
     * @return array [int exitcode, string stdout, string stderr]
     */
    public static function run(array $cmd, int $timeout = 0): array {
        if (!self::can_run() || empty($cmd)) {
            return [-1, '', 'proc_open unavailable'];
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        // The array form of $cmd bypasses the shell entirely on PHP 7.4+.
        $process = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            return [-1, '', 'failed to start process'];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $start = time();

        do {
            $status = proc_get_status($process);
            $stdout .= (string)stream_get_contents($pipes[1]);
            $stderr .= (string)stream_get_contents($pipes[2]);

            if ($timeout > 0 && (time() - $start) > $timeout) {
                proc_terminate($process, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                return [-1, $stdout, 'timed out after ' . $timeout . 's'];
            }

            if (!$status['running']) {
                break;
            }
            usleep(100000);
        } while (true);

        $stdout .= (string)stream_get_contents($pipes[1]);
        $stderr .= (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        return [$code, $stdout, $stderr];
    }

    /**
     * Build an HTML status summary of the detected tools for the settings page.
     *
     * @return string
     */
    public static function status_html(): string {
        if (!self::can_run()) {
            return \html_writer::div(
                get_string('toolstatus_noexec', 'local_scormcompress'),
                'alert alert-danger'
            );
        }

        $rows = [];
        foreach (['ffmpeg', 'ffprobe', 'magick'] as $tool) {
            $path = self::locate($tool);
            if ($path !== null) {
                $version = self::version_line($path);
                $label = get_string('toolstatus_found', 'local_scormcompress',
                    (object)['tool' => s($tool), 'version' => s($version)]);
                $rows[] = \html_writer::div('✔ ' . $label, 'text-success');
            } else {
                $rows[] = \html_writer::div('✘ ' . get_string('toolstatus_missing', 'local_scormcompress',
                    s($tool)), 'text-danger');
            }
        }

        return \html_writer::div(implode('', $rows), 'local-scormcompress-toolstatus');
    }
}
