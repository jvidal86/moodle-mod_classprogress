@mod @mod_classprogress
Feature: Export the plan against what was done
  In order to give the inspector the plan against what was done
  As a teacher of the course
  I need an export page with the same totals as the log and a spreadsheet to download

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
    And the following "local_classprogress > evaluation periods" exist:
      | schoolyear     | name         | startdate               | enddate                 |
      | Curs 2026-2027 | 1a avaluació | ##-60 days##%Y-%m-%d##  | ##today##%Y-%m-%d##     |
      | Curs 2026-2027 | 2a avaluació | ##+1 days##%Y-%m-%d##   | ##+60 days##%Y-%m-%d##  |
    And the following "activities" exist:
      | activity      | course | name           | schoolyear     | status   |
      | classprogress | C1     | Bases de dades | Curs 2026-2027 | approved |
      | classprogress | C1     | Esborrany      | Curs 2026-2027 | draft    |
    And the following "mod_classprogress > plan items" exist:
      | plan           | sessiondate            | block | title           | hours |
      | Bases de dades | ##-14 days##%Y-%m-%d## | RA1   | Presentació     | 2     |
      | Bases de dades | ##-7 days##%Y-%m-%d##  | RA1   | Entorn          | 2     |
      | Bases de dades | ##today##%Y-%m-%d##    | RA2   | Consultes       | 1.5   |
      | Bases de dades | ##+7 days##%Y-%m-%d##  | RA2   | Primer programa | 2     |
    And the following "mod_classprogress > entries" exist:
      | planitem    | content         | user     |
      | Presentació | Normes de l'aula | teacher1 |

  @REQ-EXP-001 @REQ-EXP-006 @REQ-EXP-009
  Scenario: J8 The export page offers the file and shows the same totals as the log
    Given I am on the "Bases de dades" "classprogress activity" page logged in as "teacher1"
    And I should see "Entorn" in the "[data-region='log-pending']" "css_element"
    And I should see "Consultes" in the "[data-region='log-pending']" "css_element"
    When I click on "Export" "link" in the "[data-region='log-actions']" "css_element"
    Then the following should exist in the "export-overall" table:
      | Group        | Planned | Done | Not logged | Upcoming | Coverage (%) | Planned hours | Hours done |
      | All sessions | 4       | 1    | 2          | 1        | 33           | 7.5           | 2          |
    And the following should exist in the "export-perblock" table:
      | Group | Planned | Done | Not logged | Upcoming | Coverage (%) | Planned hours |
      | RA1   | 2       | 1    | 1          | 0        | 50           | 4             |
      | RA2   | 2       | 0    | 1          | 1        | 0            | 3.5           |
    And the following should exist in the "export-perperiod" table:
      | Group        | Planned | Done | Not logged | Upcoming | Coverage (%) |
      | 1a avaluació | 3       | 1    | 2          | 0        | 33           |
      | 2a avaluació | 1       | 0    | 0          | 1        |              |
    And "Download the spreadsheet (XLSX)" "link" should exist in the "[data-region='export-download']" "css_element"
    And the "Esborrany" "mod_classprogress > Export" page shows the plan error "Sessions can be logged and exported only for an approved plan."
