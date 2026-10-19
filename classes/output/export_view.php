<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_classprogress\output;

use core\url;
use mod_classprogress\local\export_service;
use mod_classprogress\local\plan_export;

/**
 * The export page of an approved plan: the three summary tables of the file, with the same figures, and the link
 * that downloads the file.
 *
 * Group names are exported as typed, for the template to escape.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class export_view implements \renderable, \templatable {
    /**
     * Constructor.
     *
     * @param plan_export $export The plan's export.
     * @param url $downloadurl The URL that downloads the file.
     */
    public function __construct(
        /** @var plan_export The plan's export. */
        private readonly plan_export $export,
        /** @var url The URL that downloads the file. */
        private readonly url $downloadurl,
    ) {
    }

    /**
     * Exports the view for the template.
     *
     * @param \renderer_base $output The renderer.
     * @return \stdClass Template context.
     */
    #[\Override]
    public function export_for_template(\renderer_base $output): \stdClass {
        $headers = [get_string('exportfig_group', 'mod_classprogress')];
        foreach (export_service::FIGURES as $key) {
            $headers[] = get_string($key, 'mod_classprogress');
        }
        $tables = [];
        foreach (export_service::TABLES as $property => $title) {
            $rows = [];
            foreach ($this->export->$property as $group) {
                $cells = [export_service::group_name($group)];
                foreach (export_service::figures($group->figures) as $value) {
                    $cells[] = is_float($value) ? format_float($value, 2, true, true) : (string) $value;
                }
                $rows[] = ['cells' => $cells];
            }
            $tables[] = [
                'id' => 'export-' . $property,
                'title' => get_string($title, 'mod_classprogress'),
                'headers' => $headers,
                'rows' => $rows,
            ];
        }
        return (object) [
            'today' => $this->export->today,
            'downloadurl' => $this->downloadurl->out(false),
            'tables' => $tables,
        ];
    }
}
