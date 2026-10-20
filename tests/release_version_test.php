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

namespace mod_classprogress;

/**
 * The release versions in version.php (REQ-REL-001): the activity's own, its dependency on the local plugin's release,
 * and an upgrade to them that changes no table.
 *
 * @package    mod_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class release_version_test extends \advanced_testcase {
    /** @var int The version installed on the lab (0.1.0); every release must be above it. */
    private const LAST_INSTALLED = 2026101900;

    /**
     * Reads a plugin's version.php.
     *
     * @param string $component The plugin.
     * @return \stdClass The $plugin it fills.
     */
    private static function version_file(string $component): \stdClass {
        $plugin = new \stdClass();
        require(\core_component::get_component_directory($component) . '/version.php');
        return $plugin;
    }

    /**
     * The activity is released as 0.9.0 beta, for Moodle 4.5.0 and later, supported on 4.5 to 5.3, with a version
     * above every earlier one.
     *
     * @group REQ-REL-001
     */
    public function test_version_php_declares_the_release(): void {
        $plugin = self::version_file('mod_classprogress');

        $this->assertSame('mod_classprogress', $plugin->component);
        $this->assertSame('0.9.0', $plugin->release);
        $this->assertSame(MATURITY_BETA, $plugin->maturity);
        $this->assertSame(2024100700, $plugin->requires);
        $this->assertSame([405, 503], $plugin->supported);
        $this->assertGreaterThan(self::LAST_INSTALLED, $plugin->version);
    }

    /**
     * The activity depends on the local plugin's release: exactly the version in its version.php.
     *
     * @group REQ-REL-001
     */
    public function test_the_activity_depends_on_the_local_plugins_release(): void {
        $plugin = self::version_file('mod_classprogress');
        $local = self::version_file('local_classprogress');

        $this->assertSame(['local_classprogress' => $local->version], $plugin->dependencies);
        $this->assertSame($plugin->release, $local->release);
    }

    /**
     * Upgrade steps after the version on the lab touch no table and no data, so a site with 0.1.0 data keeps all of it.
     *
     * @group REQ-REL-001
     */
    public function test_the_upgrade_to_the_release_changes_no_table(): void {
        $code = file_get_contents(\core_component::get_component_directory('mod_classprogress') . '/db/upgrade.php');

        preg_match_all('/if \(\$oldversion < (\d{10})\) \{(.*?)\n    \}/s', $code, $steps, PREG_SET_ORDER);
        // Every savepoint lies in a step that was read.
        $this->assertSame(preg_match_all('/^\s+upgrade_\w+_savepoint\(true, \d{10}/m', $code), count($steps));
        foreach ($steps as [, $version, $body]) {
            if ((int) $version > self::LAST_INSTALLED) {
                $this->assertDoesNotMatchRegularExpression('/\$dbman|\$DB|_config\(/', $body, "Step {$version}");
            }
        }
    }
}
