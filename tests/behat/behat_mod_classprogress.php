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

use Behat\Mink\Exception\DriverException;
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
    /** @var bool Whether the scenario gave the browser a phone screen. */
    private bool $phonescreen = false;

    /** @var string[] Files and folders of the Catalan language pack the scenario wrote, in the order written. */
    private array $catalan = [];

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
     * Changes the settings of a plan activity as another tab or session would, after the current page was opened.
     *
     * @Given the plan activity :name is changed elsewhere
     *
     * @param string $name Name of the activity.
     */
    public function the_plan_activity_is_changed_elsewhere(string $name): void {
        global $DB;

        $plan = $DB->get_record('classprogress', ['name' => $name], '*', MUST_EXIST);
        $DB->set_field('classprogress', 'timemodified', $plan->timemodified + 1, ['id' => $plan->id]);
    }

    /**
     * Changes the entry of a planned day as another tab or session would, after the current page was opened.
     *
     * @Given the entry of :title is changed elsewhere
     *
     * @param string $title Title of the plan item the entry belongs to.
     */
    public function the_entry_is_changed_elsewhere(string $title): void {
        global $DB;

        $itemid = $DB->get_field('classprogress_item', 'id', ['title' => $title], MUST_EXIST);
        $entry = $DB->get_record('classprogress_entry', ['planitemid' => $itemid], '*', MUST_EXIST);
        $DB->set_field('classprogress_entry', 'timemodified', $entry->timemodified + 1, ['id' => $entry->id]);
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
     * Gives the browser of a JavaScript scenario a phone's screen, through Chrome's DevTools: Chrome cannot make its
     * window narrower than about 500 px, so "I change viewport size to" stops short of a phone's width.
     *
     * The screen stays until the end of the scenario (clear_screen()).
     *
     * @Given I use a phone screen :width px wide
     *
     * @param int $width Width of the screen, in CSS pixels; its height is 844.
     * @throws DriverException Without JavaScript, which has no screen.
     */
    public function i_use_a_phone_screen(int $width): void {
        if (!$this->running_javascript()) {
            throw new DriverException('A phone screen can only be used with JavaScript.');
        }
        $this->devtools('Emulation.setDeviceMetricsOverride', ['width' => $width, 'height' => 844, 'deviceScaleFactor' => 1,
            'mobile' => true]);
        $this->phonescreen = true;
    }

    /**
     * Gives the browser its own screen back after a scenario that used a phone screen; a browser already closed took
     * the phone screen with it.
     *
     * @AfterScenario @javascript
     */
    public function clear_screen(): void {
        if ($this->phonescreen && $this->getSession()->getDriver()->getWebDriver()) {
            $this->devtools('Emulation.clearDeviceMetricsOverride');
        }
        $this->phonescreen = false;
    }

    /**
     * Sends a command to the DevTools of the scenario's Chrome browser.
     *
     * @param string $command The command, such as Emulation.setDeviceMetricsOverride.
     * @param array $parameters Its parameters.
     */
    private function devtools(string $command, array $parameters = []): void {
        $webdriver = $this->getSession()->getDriver()->getWebDriver();
        (new \Facebook\WebDriver\Chrome\ChromeDevToolsDriver($webdriver))->execute($command, $parameters);
    }

    /**
     * Shows the activity to a user in Catalan: their language becomes Catalan, and a minimal Catalan language pack, its
     * langconfig.php alone, lets Moodle read the activity's own lang/ca. Moodle's own strings stay in English, since the
     * sites that run the tests have no Catalan language pack. A pack the site already has is left as it is; the one
     * written here goes after the scenario (remove_catalan()).
     *
     * @Given :username uses the activity in Catalan
     *
     * @param string $username The user.
     */
    public function uses_the_activity_in_catalan(string $username): void {
        global $CFG, $DB;

        $dir = $CFG->dataroot . '/lang/ca';
        if (!file_exists($dir . '/langconfig.php')) {
            // The folders this creates, outermost first, so that they go in the reverse order.
            foreach ([dirname($dir), $dir] as $folder) {
                if (!is_dir($folder)) {
                    check_dir_exists($folder);
                    $this->catalan[] = $folder;
                }
            }
            $config = "<?php\n\$string['thislanguage'] = 'Català';\n\$string['parentlanguage'] = '';\n";
            file_put_contents($dir . '/langconfig.php', $config);
            $this->catalan[] = $dir . '/langconfig.php';
            get_string_manager()->reset_caches();
        }
        $DB->set_field('user', 'lang', 'ca', ['username' => $username]);
    }

    /**
     * Removes the minimal Catalan language pack that a scenario wrote, so that no later scenario sees it.
     *
     * @AfterScenario
     */
    public function remove_catalan(): void {
        if (!$this->catalan) {
            return;
        }
        foreach (array_reverse($this->catalan) as $path) {
            is_dir($path) ? rmdir($path) : unlink($path);
        }
        $this->catalan = [];
        get_string_manager()->reset_caches();
    }

    /**
     * Checks that the current page does not scroll sideways: the document is no wider than the browser's viewport, and
     * no table's scrolling wrapper on it is wider than its box (REQ-UIX-004).
     *
     * @Then the page should not scroll sideways
     *
     * @throws DriverException Without JavaScript, which has no layout to measure.
     * @throws ExpectationException When the page or a table is wider than the viewport.
     */
    public function the_page_should_not_scroll_sideways(): void {
        if (!$this->running_javascript()) {
            throw new DriverException('Sideways scrolling can only be checked with JavaScript.');
        }
        $widths = $this->evaluate_script('return (function() {
            const page = document.documentElement;
            const tables = Array.from(document.querySelectorAll(".table-responsive"))
                .filter((box) => box.scrollWidth > box.clientWidth)
                .map((box) => box.scrollWidth + " in " + box.clientWidth);
            return {viewport: page.clientWidth, page: page.scrollWidth, tables: tables};
        })();');
        if ($widths['page'] > $widths['viewport'] || $widths['tables']) {
            throw new ExpectationException('The page scrolls sideways: it is ' . $widths['page'] . ' px wide in a viewport of ' .
                $widths['viewport'] . ' px; tables wider than their box: ' . json_encode($widths['tables']), $this->getSession());
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
