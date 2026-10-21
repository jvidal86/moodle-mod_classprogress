@mod @mod_classprogress
Feature: From start to finish
  In order to show the inspector the year's plan against what was done
  As a teacher of the course
  I need to set up my school year, add and approve a real plan, log its sessions with a colleague, follow what is
  pending and export it, while students see none of it

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

  # The plan's dates are fixed and the sessions logged lie before the day this was written, so every entry is late
  # and the counts asserted here hold whatever the day the scenario runs; the pending count, which grows with the
  # date, is compared between the pages instead.
  @REQ-REL-005
  Scenario: J9 A school year, a real plan imported and approved, sessions logged by two teachers, the pending count and the export; a student sees nothing
    Given I log in as "teacher1"
    And I click on "Class progress" "link" in the ".primary-navigation" "css_element"
    When I click on "New school year" "link"
    And I set the following fields to these values:
      | Name             | Curs 2026-2027        |
      | First day        | ##14 September 2026## |
      | Last day         | ##21 June 2027##      |
      | evalname[0]      | 1a avaluació          |
      | evalstartdate[0] | ##14 September 2026## |
      | evalenddate[0]   | ##27 November 2026##  |
      | evalname[1]      | 2a avaluació          |
      | evalstartdate[1] | ##30 November 2026##  |
      | evalenddate[1]   | ##26 February 2027##  |
      | evalname[2]      | 3a avaluació          |
      | evalstartdate[2] | ##1 March 2027##      |
      | evalenddate[2]   | ##21 June 2027##      |
    And I set the field "Holidays and non-teaching days" to the fixture "holidays-centre-2026-27.txt"
    And I press "Save changes"
    Then I should see "School year saved."
    And I should see "Holidays: 16. Evaluation periods: 3."

    When I add a "classprogress" activity to course "Course 1" section "1"
    And I set the following fields to these values:
      | Name            | MP 0372 Bases de dades |
      | School year     | Curs 2026-2027         |
      | First class day | ##14 September 2026##  |
      | Last class day  | ##21 June 2027##       |
      | Monday          | 2                      |
      | Friday          | 2                      |
    And I press "Save and display"
    And I click on "Import plan text" "link"
    And I set the field "Plan text" to the fixture "plans/0372.md"
    And I press "Preview"
    Then I should see "MP 0372 · Gestió de bases de dades"
    And I should see "60" in the "[data-region='preview-days']" "css_element"
    And I should see "120" in the "[data-region='preview-hours']" "css_element"
    And "[data-region='preview-errors']" "css_element" should not exist
    When I press "Save draft"
    And I click on "Approve plan" "link"
    And I press "Approve plan"
    Then I should see "Plan approved."

    When I click on "Log" "link" in the "2026-09-14" "table_row"
    And I set the field "What was done" to "Presentació del mòdul i normes de l'aula"
    And I press "Save entry"
    Then I should see "Entry saved."
    And I should see "Done" in the "2026-09-14" "table_row"
    And I should see "Late by" in the "2026-09-14" "table_row"
    When I click on "Log" "link" in the "2026-09-18" "table_row"
    And I set the following fields to these values:
      | Partly done   | 1                                   |
      | What was done | Només la primera part dels elements |
      | Reason        | Incident                            |
    And I press "Save entry"
    Then I should see "Partly done" in the "2026-09-18" "table_row"
    When I click on "Log" "link" in the "2026-09-21" "table_row"
    And I set the following fields to these values:
      | Not done | 1      |
      | Reason   | Strike |
    And I press "Save entry"
    Then I should see "Not done" in the "2026-09-21" "table_row"
    When I click on "Add an unplanned session" "link"
    And I set the following fields to these values:
      | Date of the session | ##30 September 2026##  |
      | What was done       | Repàs del diagrama E/R |
    And I press "Save entry"
    Then I should see "Added" in the "Unplanned session" "table_row"
    And I log out

    Given I am on the "MP 0372 Bases de dades" "classprogress activity" page logged in as "teacher2"
    When I click on "Log" "link" in the "2026-09-25" "table_row"
    And I set the field "What was done" to "Exercicis d'elements de les BD"
    And I press "Save entry"
    Then I should see "Entry saved."
    And I should see "Done" in the "2026-09-25" "table_row"
    And I am on the "local_classprogress > Home" page
    And I should see "Approved" in the "MP 0372 Bases de dades" "table_row"
    And the log, the overview and the export of "MP 0372 Bases de dades" show the same number of pending sessions
    And I am on the "MP 0372 Bases de dades" "classprogress activity" page
    When I click on "Export" "link" in the "[data-region='log-actions']" "css_element"
    Then the following should exist in the "export-overall" table:
      | Group        | Planned | Done | Partly done | Not done | Added | Planned hours | Hours done | Hours not done | Written late |
      | All sessions | 60      | 2    | 1           | 1        | 1     | 120           | 5          | 3              | 5            |
    And the following should exist in the "export-perblock" table:
      | Group                                 | Planned | Done | Partly done | Not done | Not logged | Upcoming | Added |
      | Bloc A · Els elements de les BD (RA1) | 6       | 2    | 1           | 1        | 2          | 0        | 0     |
      | Unplanned sessions                    | 0       | 0    | 0           | 0        | 0          | 0        | 1     |
    And the following should exist in the "export-perperiod" table:
      | Group        | Planned | Done | Partly done | Not done | Added | Planned hours | Hours done |
      | 1a avaluació | 20      | 2    | 1           | 1        | 1     | 40            | 5          |
      | 2a avaluació | 20      | 0    | 0           | 0        | 0     | 40            | 0          |
      | 3a avaluació | 20      | 0    | 0           | 0        | 0     | 40            | 0          |
    And I log out

    When I am on the "Course 1" "course" page logged in as "student1"
    Then I should not see "MP 0372 Bases de dades"
    And "Class progress" "link" should not exist in the ".primary-navigation" "css_element"
    And ".primary-navigation" "css_element" should exist
