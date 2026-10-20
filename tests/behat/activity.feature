@mod @mod_classprogress
Feature: Add a plan activity to a course, import its plan, approve it and find it locked
  In order to show the inspector a plan that cannot shift afterwards
  As a teacher of the course
  I need to add the plan activity, import my plan text, check its preview, approve it, and keep it from students

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Anna      | Puig     | teacher1@example.com |
      | teacher2 | Bernat    | Soler    | teacher2@example.com |
      | student1 | Carla     | Vidal    | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | teacher        |
      | student1 | C1     | student        |
    And the following "local_classprogress > school years" exist:
      | user     | name           | startdate  | enddate    |
      | teacher1 | Curs 2026-2027 | 2026-09-14 | 2027-06-21 |
    And the following "local_classprogress > holidays" exist:
      | schoolyear     | startdate  | label             |
      | Curs 2026-2027 | 2026-10-12 | Festa Nacional    |
      | Curs 2026-2027 | 2026-11-02 | Lliure disposició |

  @REQ-MOD-001 @REQ-CAL-001 @REQ-CAL-002 @REQ-CAL-003 @REQ-HOL-003 @REQ-MOD-003
  Scenario: An editing teacher adds the activity with a school year, class days and a timetable, and sees its sessions
    Given I log in as "teacher1"
    When I add a "classprogress" activity to course "Course 1" section "1"
    And I set the following fields to these values:
      | Name            | Bases de dades        |
      | School year     | Curs 2026-2027        |
      | First class day | ##14 September 2026## |
      | Last class day  | ##27 November 2026##  |
      | Monday          | 2                     |
      | Friday          | 2                     |
    And I press "Save and display"
    Then I should see "Draft: the plan can still be changed"
    And I should see "This plan has no days yet."
    And "Import plan text" "link" should exist
    And I should see "20 sessions, 40 hours of class"
    And I should see "5" in the "September 2026" "table_row"
    And I should see "8" in the "October 2026" "table_row"
    And I should see "7" in the "November 2026" "table_row"
    And I should see "1" in the "Festa Nacional" "table_row"
    And I should see "1" in the "Lliure disposició" "table_row"

  @REQ-MOD-002
  Scenario: A student neither sees the activity on the course page nor opens it
    Given the following "activities" exist:
      | activity      | course | name           | schoolyear     |
      | classprogress | C1     | Bases de dades | Curs 2026-2027 |
    When I am on the "Course 1" "course" page logged in as "student1"
    Then I should not see "Bases de dades"
    And I am on the "Bases de dades" "classprogress activity" page
    And the url should match "/course/view\.php"
    And I should not see "Draft: the plan can still be changed"
    And I log out
    And I am on the "Course 1" "course" page logged in as "teacher2"
    And I should see "Bases de dades"

  @REQ-PLN-005 @REQ-IMP-002 @REQ-IMP-004
  Scenario: J4 A plan with a bad date shows the error with its line and offers no saving and no approval
    Given the following "activities" exist:
      | activity      | course | name           | schoolyear     | hours0 | hours4 |
      | classprogress | C1     | Bases de dades | Curs 2026-2027 | 2      | 2      |
    And I am on the "Bases de dades" "mod_classprogress > Import" page logged in as "teacher1"
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
    Then I should see "Line 5: The day heading" in the "[data-region='preview-errors']" "css_element"
    And I should see "2026-02-30 [2h]" in the "[data-region='preview-errors']" "css_element"
    And I should see "has no date that can be read" in the "[data-region='preview-errors']" "css_element"
    And "Save draft" "button" should not exist
    And "Preview" "button" should exist
    And I am on the "Bases de dades" "mod_classprogress > View" page
    And I should see "This plan has no days yet."
    And "Approve plan" "link" should not exist

  @REQ-IMP-002 @REQ-IMP-003 @REQ-IMP-004 @REQ-LCK-001 @REQ-LCK-002 @REQ-MOD-003 @REQ-MOD-004
  Scenario: J5 A clean plan is previewed, saved as a draft, approved by another teacher after its warnings are confirmed, and is then read-only
    Given the following "activities" exist:
      | activity      | course | name           | schoolyear     | hours0 | hours4 |
      | classprogress | C1     | Bases de dades | Curs 2026-2027 | 2      | 2      |
    And I am on the "Bases de dades" "classprogress activity" page logged in as "teacher1"
    When I click on "Import plan text" "link"
    Then I should see "Instructions for my AI assistant"
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
    Then I should see "MP 0372 · Gestió de bases de dades"
    And I should see "3" in the "[data-region='preview-days']" "css_element"
    And I should see "6" in the "[data-region='preview-hours']" "css_element"
    And I should see "Line 8: The day 2026-10-12 is a holiday: Festa Nacional." in the "[data-region='preview-warnings']" "css_element"
    When I press "Save draft"
    Then I should see "Draft saved."
    And I should see "Draft: the plan can still be changed"
    And I should see "Normes de l'aula"
    And I should see "RA2 · Escriu i prova codi"
    And I log out
    And I am on the "Bases de dades" "classprogress activity" page logged in as "teacher2"
    When I click on "Approve plan" "link"
    Then I should see "Approval is final."
    And I should see "Line 8: The day 2026-10-12 is a holiday: Festa Nacional."
    When I press "Approve plan"
    Then I should see "Confirm that you have read the warnings."
    When I set the field "I have read the warnings" to "1"
    And I press "Approve plan"
    Then I should see "Plan approved."
    And I should see "Session log"
    And "Import plan text" "link" should not exist
    And "Approve plan" "link" should not exist
    When I click on "Plan and calendar" "link"
    Then I should see "Approved on"
    And I should see "Normes de l'aula"
    And the "Bases de dades" "mod_classprogress > Import" page shows the plan error "This plan is approved and can no longer be changed."
    And the "Bases de dades" "mod_classprogress > Approval" page shows the plan error "This plan is approved and can no longer be changed."
    And I log out
    And I am on the "Bases de dades" "classprogress activity editing" page logged in as "teacher1"
    And I should see "This plan is approved: its school year, class days and timetable can no longer be changed."
    And I am on the "local_classprogress > Home" page
    And I should see "Approved" in the "Bases de dades" "table_row"
    And I should see "Course 1" in the "Bases de dades" "table_row"

  @REQ-DAT-003
  Scenario: Saving the settings of a plan activity that was changed elsewhere after the form was opened is refused and saves nothing
    Given the following "activities" exist:
      | activity      | course | name           | schoolyear     | hours0 |
      | classprogress | C1     | Bases de dades | Curs 2026-2027 | 2      |
    And I am on the "Bases de dades" "classprogress activity editing" page logged in as "teacher1"
    And the plan activity "Bases de dades" is changed elsewhere
    When I set the field "Name" to "Bases de dades II"
    And I press "Save and display"
    Then I should see "This was changed elsewhere; reload to see the latest version"
    And I am on the "Course 1" "course" page
    And I should see "Bases de dades"
    And I should not see "Bases de dades II"
