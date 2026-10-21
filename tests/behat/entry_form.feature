@mod @mod_classprogress
Feature: Log a session with few clicks
  In order to log each session quickly at the end of class, later on a laptop or on my phone
  As a teacher of the course
  I need a status chosen with one click, a cancellation that asks only for its reason, a way on to the next pending
  session, and the session named once on its form and on its history

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
      | plan           | sessiondate            | block | title           | content               | hours |
      | Bases de dades | ##-7 days##%Y-%m-%d##  | RA1   | Presentació     | Normes de l'aula      | 2     |
      | Bases de dades | ##-3 days##%Y-%m-%d##  | RA1   | Model E/R       | Entitats i atributs   | 2     |
      | Bases de dades | ##today##%Y-%m-%d##    | RA1   | Entorn          | Instal·lació de l'IDE | 2     |
      | Bases de dades | ##+7 days##%Y-%m-%d##  | RA2   | Primer programa | Hola, món             | 2     |

  @javascript @REQ-UIX-005
  Scenario: The status is one click of three, and only a deviation asks for its reason, note and measure
    Given I am on the "Bases de dades" "classprogress activity" page logged in as "teacher1"
    When I click on "Log" "link" in the "Presentació" "table_row"
    Then the field "Done" matches value "1"
    And the field "Partly done" matches value ""
    And the field "Not done" matches value ""
    And I should not see "Reason (required)"
    And I should not see "Corrective measure"
    When I set the field "Not done" to "1"
    Then I should see "Reason (required)"
    And I should see "Note about the reason"
    And I should see "Corrective measure"
    And I should not see "Continue in the next session"
    When I set the field "Partly done" to "1"
    Then I should see "Continue in the next session"
    And I should see "Reason (required)"
    When I set the field "Done" to "1"
    Then I should not see "Reason (required)"
    And I should not see "Continue in the next session"
    When I set the following fields to these values:
      | Not done | 1     |
      | Reason   | Trip  |
    And I press "Save entry"
    Then I should see "Not done" in the "Presentació" "table_row"

  @REQ-UIX-005 @REQ-LOG-009
  Scenario: A session after today can only be cancelled: its status is fixed and only its reason, note and measure are asked
    Given I am on the "Bases de dades" "classprogress activity" page logged in as "teacher1"
    When I click on "Log" "link" in the "Primer programa" "table_row"
    Then I should see "Not done (cancellation known in advance)"
    And "What was done" "field" should not exist
    And "Continue in the next session" "field" should not exist
    When I set the following fields to these values:
      | Reason                | Trip              |
      | Note about the reason | Sortida al museu  |
    And I press "Save entry"
    Then I should see "Entry saved."
    And I should see "Not done" in the "Primer programa" "table_row"

  @REQ-UIX-006
  Scenario: Save and log the next pending session goes from one pending session to the next, oldest first
    Given I am on the "Bases de dades" "classprogress activity" page logged in as "teacher1"
    When I click on "Log" "link" in the "Presentació" "table_row"
    And I set the field "What was done" to "Normes explicades"
    And I press "Save and log the next pending session (2 left)"
    Then I should see "Entry saved."
    And I should see "Entitats i atributs" in the "[data-region='planned-day']" "css_element"
    When I set the field "What was done" to "Diagrama de la botiga"
    And I press "Save and log the next pending session (1 left)"
    Then I should see "Entry saved."
    And I should see "Instal·lació de l'IDE" in the "[data-region='planned-day']" "css_element"
    And "Save and log the next pending session" "button" should not exist
    When I set the field "What was done" to "IDE instal·lat"
    And I press "Save entry"
    Then I should see "Entry saved."
    And I should see "Nothing pending: every session up to today is logged."
    And I should see "Done" in the "Model E/R" "table_row"

  @REQ-UIX-007
  Scenario: The entry form names its session once and puts the entry's dates and links after its buttons; the history names the session and leads back to the log
    Given the following "mod_classprogress > entries" exist:
      | planitem    | content           | user     |
      | Presentació | Normes explicades | teacher1 |
    And I am on the "Bases de dades" "classprogress activity" page logged in as "teacher1"
    When I click on "Edit entry" "link" in the "Presentació" "table_row"
    Then I should see "##-7 days##%Y-%m-%d##" in the "#region-main h3" "css_element"
    And I should see "Presentació" in the "#region-main h3" "css_element"
    And I should see "Normes de l'aula" in the "[data-region='planned-day']" "css_element"
    And I should not see "Presentació" in the "[data-region='planned-day']" "css_element"
    And "Save entry" "button" should appear before "[data-region='entry-written']" "css_element"
    And I should see "Written on" in the "[data-region='entry-written']" "css_element"
    And "Delete entry" "link" should exist in the "[data-region='entry-written']" "css_element"
    When I click on "History" "link" in the "[data-region='entry-written']" "css_element"
    Then I should see "History of the session" in the "#region-main h3" "css_element"
    And I should see "##-7 days##%Y-%m-%d##" in the "#region-main h3" "css_element"
    And I should see "Presentació" in the "#region-main h3" "css_element"
    And "Normes explicades" "text" should appear before "Back to the session log" "button"
    When I press "Back to the session log"
    Then I should see "Presentació" in the "[data-region='log-logged']" "css_element"
