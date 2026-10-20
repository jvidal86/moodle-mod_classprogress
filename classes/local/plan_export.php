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

namespace mod_classprogress\local;

use local_classprogress\local\summary_group;

/**
 * The export of an approved plan as export_service::build() reads it: the header block, the Plan vs done rows and the
 * three summary tables, all as the page and the file show them.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_export {
    /**
     * Creates the export.
     *
     * @param \stdClass $plan The plan row.
     * @param string $today The exporting teacher's own day, yyyy-mm-dd.
     * @param array $header Field => value of the header block, in order (the label is the string exportlabel_<field>):
     *     text, or a number for the planned sessions and hours.
     * @param array[] $rows The Plan vs done rows, in order, each with the export_service::COLUMNS keys: text (empty
     *     for none), hours a float and dayslate an int (null for none).
     * @param summary_group[] $overall The overall table.
     * @param summary_group[] $perblock The per block table.
     * @param summary_group[] $perperiod The per evaluation period table.
     * @param string $filename The download name, cleaned.
     */
    public function __construct(
        /** @var \stdClass The plan row. */
        public readonly \stdClass $plan,
        /** @var string The exporting teacher's own day, yyyy-mm-dd. */
        public readonly string $today,
        /** @var array Field => value of the header block, in order. */
        public readonly array $header,
        /** @var array[] The Plan vs done rows, in order, each with the export_service::COLUMNS keys. */
        public readonly array $rows,
        /** @var summary_group[] The overall table. */
        public readonly array $overall,
        /** @var summary_group[] The per block table. */
        public readonly array $perblock,
        /** @var summary_group[] The per evaluation period table. */
        public readonly array $perperiod,
        /** @var string The download name, cleaned. */
        public readonly string $filename,
    ) {
    }
}
