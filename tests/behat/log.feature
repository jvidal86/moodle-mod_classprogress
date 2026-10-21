@mod @mod_classprogress
Feature: Log what was done in each session
  In order to show the inspector what was done against the plan
  As a teacher of the course
  I need to log each session, also after its day, and keep every version of what we wrote

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Anna      | Puig     | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "local_classprogress > school years" exist:
      | user     | name           | startdate                 | enddate                   |
      | teacher1 | Curs 2026-2027 | ##-60 days##%Y-%m-%d##    | ##+60 days##%Y-%m-%d##    |
    And the following "activities" exist:
      | activity      | course | name           | schoolyear     | status   |
      | classprogress | C1     | Bases de dades | Curs 2026-2027 | approved |
    And the following "mod_classprogress > plan items" exist:
      | plan           | sessiondate            | title           | content               |
      | Bases de dades | ##-7 days##%Y-%m-%d##  | Presentació     | Normes de l'aula      |
      | Bases de dades | ##today##%Y-%m-%d##    | Entorn          | Instal·lació de l'IDE |
      | Bases de dades | ##+7 days##%Y-%m-%d##  | Primer programa | Hola, món             |

  @REQ-LOG-001 @REQ-LOG-002 @REQ-LOG-003 @REQ-LOG-004 @REQ-LOG-006 @REQ-LOG-007
  Scenario: J6 A session logged on its day is not late; an older one logged afterwards shows how late it was written
    Given I am on the "Bases de dades" "classprogress activity" page logged in as "teacher1"
    And I should see "Presentació" in the "[data-region='log-pending']" "css_element"
    And I should see "Entorn" in the "[data-region='log-pending']" "css_element"
    And I should see "Primer programa" in the "[data-region='log-upcoming']" "css_element"
    And "Presentació" "table_row" should appear before "Entorn" "table_row"
    When I click on "Log" "link" in the "Entorn" "table_row"
    Then I should see "Instal·lació de l'IDE" in the "[data-region='planned-day']" "css_element"
    And I set the field "What was done" to "IDE instal·lat a tots els ordinadors"
    And I press "Save entry"
    And I should see "Entry saved."
    And I should see "Entorn" in the "[data-region='log-logged']" "css_element"
    And I should see "Done" in the "Entorn" "table_row"
    And I should not see "Late by" in the "Entorn" "table_row"
    When I click on "Log" "link" in the "Presentació" "table_row"
    And I set the following fields to these values:
      | Partly done                  | 1                  |
      | What was done                | Només les normes   |
      | Reason                       | Incident           |
      | Continue in the next session | 1                  |
    And I press "Save entry"
    Then I should see "Partly done" in the "Presentació" "table_row"
    And I should see "Late by 7 days" in the "Presentació" "table_row"
    And I should see "Nothing pending: every session up to today is logged."
    When I click on "Edit entry" "link" in the "Entorn" "table_row"
    Then I should see "Només les normes" in the "[data-region='left-over']" "css_element"
    And I should see "Written on" in the "[data-region='entry-written']" "css_element"
    And I am on the "Bases de dades" "classprogress activity" page
    When I click on "Add an unplanned session" "link"
    And I set the field "What was done" to "Repàs abans de l'examen"
    And I press "Save entry"
    Then I should see "Unplanned session" in the "[data-region='log-logged']" "css_element"
    And I should see "Added" in the "Unplanned session" "table_row"

  @REQ-LOG-005 @REQ-HIS-001 @REQ-HIS-002
  Scenario: J7 Editing an entry keeps the earlier version in its history; deleting it keeps the history
    Given the following "mod_classprogress > entries" exist:
      | planitem    | content           | user     |
      | Presentació | Normes explicades | teacher1 |
    And I am on the "Bases de dades" "classprogress activity" page logged in as "teacher1"
    When I click on "Edit entry" "link" in the "Presentació" "table_row"
    And I set the field "What was done" to "Normes i prova diagnòstica"
    And I press "Save entry"
    And I click on "History" "link" in the "Presentació" "table_row"
    Then I should see "Normes i prova diagnòstica"
    And I should see "Normes explicades"
    And "Edited" "text" should appear before "Created" "text"
    And "Normes i prova diagnòstica" "text" should appear before "Normes explicades" "text"
    When I press "Back to the session log"
    And I click on "Edit entry" "link" in the "Presentació" "table_row"
    And I click on "Delete entry" "link"
    And I should see "Delete the entry for the session of"
    And I press "Continue"
    Then I should see "Entry deleted. Its history is kept."
    And I should see "Presentació" in the "[data-region='log-pending']" "css_element"
    When I click on "History of deleted entries" "link"
    Then "Deleted" "text" should appear before "Edited" "text"
    And "Edited" "text" should appear before "Created" "text"
    And I should see "Normes i prova diagnòstica"
    And I should see "Normes explicades"

  @REQ-LOG-005 @REQ-DAT-003
  Scenario: Saving or deleting an entry that was changed elsewhere after its page was opened is refused and changes nothing
    Given the following "mod_classprogress > entries" exist:
      | planitem    | content           | user     |
      | Presentació | Normes explicades | teacher1 |
    And I am on the "Bases de dades" "classprogress activity" page logged in as "teacher1"
    When I click on "Edit entry" "link" in the "Presentació" "table_row"
    And the entry of "Presentació" is changed elsewhere
    And I set the field "What was done" to "Normes i prova diagnòstica"
    And I press "Save entry"
    Then I should see "This was changed elsewhere; reload to see the latest version"
    And the field "What was done" matches value "Normes i prova diagnòstica"
    And I am on the "Bases de dades" "classprogress activity" page
    When I click on "Edit entry" "link" in the "Presentació" "table_row"
    And I click on "Delete entry" "link"
    And the entry of "Presentació" is changed elsewhere
    And I press "Continue"
    Then I should see "This was changed elsewhere; reload to see the latest version"
    And I am on the "Bases de dades" "classprogress activity" page
    And I should see "Presentació" in the "[data-region='log-logged']" "css_element"
    When I click on "History" "link" in the "Presentació" "table_row"
    Then I should see "Normes explicades"
    And I should not see "Normes i prova diagnòstica"
    And I should not see "Deleted"
