@mod @mod_classprogress @javascript @accessibility @REQ-REL-007
Feature: The activity pages meet the accessibility standards
  In order to keep the plan and the log with a screen reader or the keyboard
  As a teacher of the course
  I need every page of the plan activity to pass Moodle's automated accessibility check

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
      | user     | name           | startdate  | enddate    |
      | teacher1 | Curs 2026-2027 | 2026-09-14 | 2027-06-21 |
    And the following "local_classprogress > holidays" exist:
      | schoolyear     | startdate  | label          |
      | Curs 2026-2027 | 2026-10-12 | Festa Nacional |
    And the following "local_classprogress > evaluation periods" exist:
      | schoolyear     | name         | startdate  | enddate    |
      | Curs 2026-2027 | 1a avaluació | 2026-09-14 | 2026-11-27 |
      | Curs 2026-2027 | 2a avaluació | 2026-11-30 | 2027-02-26 |

  Scenario: The settings form, the import page and its previews, a draft plan and its approval, empty and with errors
    Given the following "activities" exist:
      | activity      | course | name           | schoolyear     | hours0 | hours4 |
      | classprogress | C1     | Bases de dades | Curs 2026-2027 | 2      | 2      |
    And I am on the "Bases de dades" "classprogress activity" page logged in as "teacher1"
    And I should see "This plan has no days yet."
    Then the page should meet accessibility standards
    And I am on the "Bases de dades" "classprogress activity editing" page
    And the page should meet accessibility standards
    When I set the field "Last class day" to "##1 September 2026##"
    And I press "Save and display"
    Then I should see "The last day is before the first day."
    And the page should meet accessibility standards
    And I am on the "Bases de dades" "mod_classprogress > Import" page
    And the page should meet accessibility standards
    When I set the field "Plan text" to multiline:
      """
      # MP 0372 · Gestió de bases de dades
      ## RA1 · Reconeix els elements
      ### 2026-09-14 [2h]
      - Presentació
      ### 2026-02-30 [2h]
      - Un dia que no existeix
      """
    And I press "Preview"
    Then I should see "has no date that can be read" in the "[data-region='preview-errors']" "css_element"
    And the page should meet accessibility standards
    When I set the field "Plan text" to multiline:
      """
      # MP 0372 · Gestió de bases de dades
      ## RA1 · Reconeix els elements
      ### 2026-09-14 [2h] Presentació
      - Normes de l'aula
      ### dv 18/09/2026
      - Entorn de treball
      ## RA2 · Escriu i prova codi
      ### 2026-10-12
      - Primer programa
      """
    And I press "Preview"
    Then I should see "The day 2026-10-12 is a holiday: Festa Nacional." in the "[data-region='preview-warnings']" "css_element"
    And the page should meet accessibility standards
    When I press "Save draft"
    Then I should see "Normes de l'aula"
    And the page should meet accessibility standards
    When I click on "Approve plan" "link"
    Then I should see "Approval is final."
    And the page should meet accessibility standards
    When I press "Approve plan"
    Then I should see "Confirm that you have read the warnings."
    And the page should meet accessibility standards

  Scenario: The log, the entry form empty and with an error, the histories, the plan, the export and the top-menu page
    Given the following "activities" exist:
      | activity      | course | name           | schoolyear     | status   |
      | classprogress | C1     | Bases de dades | Curs 2026-2027 | approved |
    And the following "mod_classprogress > plan items" exist:
      | plan           | sessiondate | block | title       | content          | hours |
      | Bases de dades | 2026-09-14  | RA1   | Presentació | Normes de l'aula | 2     |
      | Bases de dades | 2026-09-18  | RA1   | Entorn      | Instal·lació     | 2     |
      | Bases de dades | 2027-01-15  | RA2   | Consultes   | SELECT           | 2     |
    And the following "mod_classprogress > entries" exist:
      | planitem    | content           | user     |
      | Presentació | Normes explicades | teacher1 |
    And I am on the "Bases de dades" "classprogress activity" page logged in as "teacher1"
    And I should see "Entorn" in the "[data-region='log-pending']" "css_element"
    Then the page should meet accessibility standards
    When I click on "Log" "link" in the "Entorn" "table_row"
    Then the page should meet accessibility standards
    When I set the following fields to these values:
      | Partly done   | 1             |
      | What was done | Només l'IDE   |
    And I press "Save entry"
    Then I should see "Choose why the session was not fully done."
    And the page should meet accessibility standards
    And I am on the "Bases de dades" "classprogress activity" page
    When I click on "Add an unplanned session" "link"
    Then the page should meet accessibility standards
    And I am on the "Bases de dades" "classprogress activity" page
    When I click on "Edit entry" "link" in the "Presentació" "table_row"
    And I set the field "What was done" to "Normes i prova diagnòstica"
    And I press "Save entry"
    And I click on "History" "link" in the "Presentació" "table_row"
    Then I should see "Normes explicades"
    And the page should meet accessibility standards
    And I am on the "Bases de dades" "classprogress activity" page
    When I click on "Edit entry" "link" in the "Presentació" "table_row"
    And I click on "Delete entry" "link"
    Then I should see "Delete the entry for the session of"
    And the page should meet accessibility standards
    When I press "Continue"
    And I click on "History of deleted entries" "link"
    Then I should see "Normes i prova diagnòstica"
    And the page should meet accessibility standards
    And I am on the "Bases de dades" "mod_classprogress > Plan" page
    And I should see "Approved on"
    And the page should meet accessibility standards
    And I am on the "Bases de dades" "mod_classprogress > Export" page
    And "Download the spreadsheet (XLSX)" "link" should exist
    And the page should meet accessibility standards
    And I am on the "local_classprogress > Home" page
    And I should see "Approved" in the "Bases de dades" "table_row"
    And the page should meet accessibility standards

  Scenario: A session is logged and the export tables are reached with the keyboard alone
    Given the following "activities" exist:
      | activity      | course | name           | schoolyear     | status   |
      | classprogress | C1     | Bases de dades | Curs 2026-2027 | approved |
    And the following "mod_classprogress > plan items" exist:
      | plan           | sessiondate | block | title       | content          | hours |
      | Bases de dades | 2026-09-14  | RA1   | Presentació | Normes de l'aula | 2     |
    And I am on the "Bases de dades" "classprogress activity" page logged in as "teacher1"
    When I set the focus on the "[data-region='log-pending'] a" "css_element"
    And I press the enter key
    Then I should see "Normes de l'aula"
    And I set the field "What was done" to "Normes explicades"
    And I set the focus on the "Save entry" "button"
    And I press the enter key
    Then I should not see "Presentació" in the "[data-region='log-pending']" "css_element"
    And I am on the "Bases de dades" "mod_classprogress > Export" page
    When I set the focus on the "Download the spreadsheet (XLSX)" "link"
    And I press the tab key
    Then the focused element is "[role='region'][tabindex='0']" "css_element"
