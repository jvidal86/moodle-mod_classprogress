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

/**
 * Named pages and steps for the class progress activity.
 *
 * @package    mod_classprogress
 * @category   test
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;

/**
 * Named pages and steps for the class progress activity.
 *
 * Pages of one activity, by its name: "mod_classprogress > View", "Import", "Approval", "Plan", "Deleted entries" and
 * "Export".
 * The activity's settings are Moodle's "<name>" "classprogress activity editing" page.
 *
 * @package    mod_classprogress
 * @category   test
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_classprogress extends behat_base {
    /**
     * Returns the URL of a page of one plan activity.
     *
     * @param string $type Page type: View, Import, Approval, Plan, Deleted entries or Export.
     * @param string $identifier Name of the activity.
     * @return moodle_url The URL.
     * @throws Exception For an unknown type.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        $cmid = $this->get_cm_by_activity_name('classprogress', $identifier)->id;
        $pages = ['view' => 'view.php', 'import' => 'import.php', 'approval' => 'approve.php', 'plan' => 'plan.php',
            'deleted entries' => 'history.php', 'export' => 'export.php'];
        $page = $pages[strtolower($type)] ?? null;
        if ($page === null) {
            throw new Exception("Unrecognised mod_classprogress page type \"{$type}\".");
        }
        return new moodle_url('/mod/classprogress/' . $page, ['id' => $cmid]);
    }

    /**
     * Opens a page of a plan activity and checks that Moodle refuses it with the given error.
     *
     * @Then the :identifier :page page shows the plan error :message
     *
     * @param string $identifier Name of the activity.
     * @param string $page Page type with the component, such as "mod_classprogress > Import".
     * @param string $message Text the error must contain.
     * @throws Exception For a page of another component.
     */
    public function the_page_shows_the_plan_error(string $identifier, string $page, string $message): void {
        $prefix = 'mod_classprogress > ';
        if (strpos($page, $prefix) !== 0) {
            throw new Exception("Unrecognised page \"{$page}\": it must start with \"{$prefix}\".");
        }
        $url = $this->resolve_page_instance_url(substr($page, strlen($prefix)), $identifier);
        $previous = $this->getSession()->getCurrentUrl();
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
        $box = $this->getSession()->getPage()->find('xpath', "//div[@data-rel='fatalerror']");
        $text = $box ? $box->getText() : '';
        if (strpos($text, $message) === false) {
            $found = $box ? "the error \"{$text}\"" : 'no error';
            throw new ExpectationException("Expected the error \"{$message}\" on {$url}, found {$found}.", $this->getSession());
        }
        // The check that Behat runs after every step fails on any page with an error box.
        $this->getSession()->visit($previous);
    }

    /**
     * Sets a field to the text of one of the activity's test fixtures, such as a real plan or a school's holidays.
     *
     * @When I set the field :field to the fixture :path
     *
     * @param string $field Field label or name.
     * @param string $path File under tests/fixtures, such as plans/0372.md.
     * @throws Exception For a path outside tests/fixtures or a missing file.
     */
    public function i_set_the_field_to_the_fixture(string $field, string $path): void {
        $file = __DIR__ . '/../fixtures/' . $path;
        if (str_contains($path, '..') || !is_file($file)) {
            throw new Exception("No fixture \"{$path}\" in tests/fixtures.");
        }
        $this->execute('behat_forms::i_set_the_field_to', [$field, file_get_contents($file)]);
    }

    /**
     * Checks that the log page, the top-menu overview and the export page give the same number of pending sessions of
     * a plan activity, and that there are some (REQ-EXP-009, REQ-MOD-005).
     *
     * The number depends on today's date, so a scenario on a real plan with fixed dates compares the three pages
     * instead of expecting a number.
     *
     * @Then the log, the overview and the export of :identifier show the same number of pending sessions
     *
     * @param string $identifier Name of the activity.
     * @throws ExpectationException When a page lacks the number or the numbers differ.
     */
    public function the_pending_sessions_agree(string $identifier): void {
        $session = $this->getSession();
        $previous = $session->getCurrentUrl();
        $name = behat_context_helper::escape($identifier);
        $notlogged = behat_context_helper::escape(get_string('exportfig_notlogged', 'mod_classprogress'));
        $counts = [];

        $this->visit_page($this->resolve_page_instance_url('View', $identifier));
        $counts['log page'] = (string) count($session->getPage()->findAll('css', "[data-region='log-pending'] tbody tr"));

        $this->visit_page(new moodle_url('/local/classprogress/index.php'));
        $counts['overview'] = $this->cell_text(
            "//section[@data-region='plan-activities']//tbody/tr[td[1][normalize-space(.) = {$name}]]/td[4]"
        );

        $this->visit_page($this->resolve_page_instance_url('Export', $identifier));
        $column = "count(//table[@id='export-overall']//thead//th[normalize-space(.) = {$notlogged}]/preceding-sibling::th) + 1";
        $counts['export'] = $this->cell_text("//table[@id='export-overall']//tbody/tr[1]/td[{$column}]");

        $session->visit($previous);
        if (count(array_unique($counts)) !== 1 || (int) $counts['log page'] < 1) {
            throw new ExpectationException('Pending sessions of "' . $identifier . '" differ or are none: ' .
                json_encode($counts), $session);
        }
    }

    /**
     * Opens a page of the site.
     *
     * @param moodle_url $url The page.
     */
    private function visit_page(moodle_url $url): void {
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
    }

    /**
     * The text of a table cell of the current page.
     *
     * @param string $xpath XPath of the cell.
     * @return string|null Its text, trimmed; null when the page has no such cell.
     */
    private function cell_text(string $xpath): ?string {
        $cell = $this->getSession()->getPage()->find('xpath', $xpath);
        return $cell ? trim($cell->getText()) : null;
    }
}
