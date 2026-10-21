@mod @mod_classprogress
Feature: The log page leads with the session to log
  In order to log at the end of class, later on a laptop or on my phone, and to read what is planned next
  As a teacher of the course
  I need the session to log first, short lists that open to show each session's plan, and a page that fits my phone

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

  @REQ-UIX-001
  Scenario: On a day with a session the card offers to log it; once it is logged the card shows the next session
    Given the following "mod_classprogress > plan items" exist:
      | plan           | sessiondate            | block | title           | content               | hours |
      | Bases de dades | ##-7 days##%Y-%m-%d##  | RA1   | Presentació     | Normes de l'aula      | 2     |
      | Bases de dades | ##today##%Y-%m-%d##    | RA1   | Entorn          | Instal·lació de l'IDE | 2     |
      | Bases de dades | ##+7 days##%Y-%m-%d##  | RA2   | Primer programa | Hola, món             | 1.5   |
    And I am on the "Bases de dades" "classprogress activity" page logged in as "teacher1"
    Then I should see "Today" in the "[data-region='log-card']" "css_element"
    And I should see "##today##%Y-%m-%d##" in the "[data-region='log-card']" "css_element"
    And I should see "Entorn" in the "[data-region='log-card']" "css_element"
    And I should see "2 h · RA1" in the "[data-region='log-card']" "css_element"
    And I should see "Instal·lació de l'IDE" in the "[data-region='log-card']" "css_element"
    And "[data-region='log-card']" "css_element" should appear before "[data-region='log-pending']" "css_element"
    # Today's session stays in the pending list, whose rows the export counts as not logged (REQ-EXP-009).
    And I should see "Entorn" in the "[data-region='log-pending']" "css_element"
    And "Log a cancellation" "link" should not exist
    When I click on "Log this session" "link" in the "[data-region='log-card']" "css_element"
    And I set the field "What was done" to "IDE instal·lat"
    And I press "Save entry"
    Then I should see "Next session" in the "[data-region='log-card']" "css_element"
    And I should see "##+7 days##%Y-%m-%d##" in the "[data-region='log-card']" "css_element"
    And I should see "Primer programa" in the "[data-region='log-card']" "css_element"
    And I should see "1.5 h · RA2" in the "[data-region='log-card']" "css_element"
    And I should see "Hola, món" in the "[data-region='log-card']" "css_element"
    And "Log this session" "link" should not exist
    When I click on "Log a cancellation" "link" in the "[data-region='log-card']" "css_element"
    Then I should see "Not done (cancellation known in advance)"

  @REQ-UIX-001
  Scenario: On a day without a session the card shows the next one; with no session left there is no card
    Given the following "mod_classprogress > plan items" exist:
      | plan           | sessiondate            | block | title   | content          | hours |
      | Bases de dades | ##-2 days##%Y-%m-%d##  | RA1   | Romànic | Arcs de mig punt | 2     |
      | Bases de dades | ##+3 days##%Y-%m-%d##  | RA1   | Gòtic   | Arcs apuntats    | 2     |
    And I am on the "Bases de dades" "classprogress activity" page logged in as "teacher1"
    Then I should see "Next session" in the "[data-region='log-card']" "css_element"
    And I should see "Gòtic" in the "[data-region='log-card']" "css_element"
    And I should see "Arcs apuntats" in the "[data-region='log-card']" "css_element"
    And I should not see "Romànic" in the "[data-region='log-card']" "css_element"
    And "Log this session" "link" should not exist
    When I click on "Log a cancellation" "link" in the "[data-region='log-card']" "css_element"
    And I set the field "Reason" to "Strike"
    And I press "Save entry"
    Then I should see "Entry saved."
    And "[data-region='log-card']" "css_element" should not exist
    And I should see "Romànic" in the "[data-region='log-pending']" "css_element"

  @REQ-UIX-002
  Scenario: Pending sessions come first, then the next five upcoming and the latest five logged, each with the rest folded
    Given the following "mod_classprogress > plan items" exist:
      | plan           | sessiondate             | title     | content   |
      | Bases de dades | ##-30 days##%Y-%m-%d##  | Passada 1 | Pla P1    |
      | Bases de dades | ##-29 days##%Y-%m-%d##  | Passada 2 | Pla P2    |
      | Bases de dades | ##-28 days##%Y-%m-%d##  | Passada 3 | Pla P3    |
      | Bases de dades | ##-27 days##%Y-%m-%d##  | Passada 4 | Pla P4    |
      | Bases de dades | ##-26 days##%Y-%m-%d##  | Passada 5 | Pla P5    |
      | Bases de dades | ##-25 days##%Y-%m-%d##  | Passada 6 | Pla P6    |
      | Bases de dades | ##-24 days##%Y-%m-%d##  | Passada 7 | Pla P7    |
      | Bases de dades | ##-5 days##%Y-%m-%d##   | Pendent A | Pla A     |
      | Bases de dades | ##-4 days##%Y-%m-%d##   | Pendent B | Pla B     |
      | Bases de dades | ##+1 days##%Y-%m-%d##   | Futura 1  | Pla F1    |
      | Bases de dades | ##+2 days##%Y-%m-%d##   | Futura 2  | Pla F2    |
      | Bases de dades | ##+3 days##%Y-%m-%d##   | Futura 3  | Pla F3    |
      | Bases de dades | ##+4 days##%Y-%m-%d##   | Futura 4  | Pla F4    |
      | Bases de dades | ##+5 days##%Y-%m-%d##   | Futura 5  | Pla F5    |
      | Bases de dades | ##+6 days##%Y-%m-%d##   | Futura 6  | Pla F6    |
      | Bases de dades | ##+7 days##%Y-%m-%d##   | Futura 7  | Pla F7    |
    And the following "mod_classprogress > entries" exist:
      | planitem  | content | user     |
      | Passada 1 | Fet 1   | teacher1 |
      | Passada 2 | Fet 2   | teacher1 |
      | Passada 3 | Fet 3   | teacher1 |
      | Passada 4 | Fet 4   | teacher1 |
      | Passada 5 | Fet 5   | teacher1 |
      | Passada 6 | Fet 6   | teacher1 |
      | Passada 7 | Fet 7   | teacher1 |
    And I am on the "Bases de dades" "classprogress activity" page logged in as "teacher1"
    Then I should see "Pending sessions (2)" in the "[data-region='log-pending']" "css_element"
    And I should see "Upcoming sessions (7)" in the "[data-region='log-upcoming']" "css_element"
    And I should see "Logged sessions (7)" in the "[data-region='log-logged']" "css_element"
    And "[data-region='log-pending']" "css_element" should appear before "[data-region='log-upcoming']" "css_element"
    And "[data-region='log-upcoming']" "css_element" should appear before "[data-region='log-logged']" "css_element"
    And "[data-region='log-logged']" "css_element" should appear before "History of deleted entries" "link"
    And "Pendent A" "table_row" should appear before "Pendent B" "table_row"
    And "Futura 1" "table_row" should appear before "Futura 5" "table_row"
    And I should see "All upcoming sessions (7)" in the "[data-region='log-upcoming']" "css_element"
    And I should see "Futura 6" in the "[data-region='log-upcoming-more']" "css_element"
    And I should see "Futura 7" in the "[data-region='log-upcoming-more']" "css_element"
    And I should not see "Futura 5" in the "[data-region='log-upcoming-more']" "css_element"
    And "Passada 7" "table_row" should appear before "Passada 3" "table_row"
    And I should see "All logged sessions (7)" in the "[data-region='log-logged']" "css_element"
    And I should see "Passada 2" in the "[data-region='log-logged-more']" "css_element"
    And I should see "Passada 1" in the "[data-region='log-logged-more']" "css_element"
    And I should not see "Passada 3" in the "[data-region='log-logged-more']" "css_element"
    And "Passada 2" "table_row" should appear before "Passada 1" "table_row"

  @javascript @accessibility @REQ-UIX-002 @REQ-UIX-003
  Scenario: A session's title opens in place to show its plan; a title left empty is named by its plan; the folded rows open too
    Given the following "mod_classprogress > plan items" exist:
      | plan           | sessiondate            | block | title           | content                                                                             | hours |
      | Bases de dades | ##-3 days##%Y-%m-%d##  | RA1   | Presentació     | Normes de l'aula                                                                    | 2     |
      | Bases de dades | ##+1 days##%Y-%m-%d##  | RA2   | Primer programa | Hola, món                                                                           | 1.5   |
      | Bases de dades | ##+2 days##%Y-%m-%d##  | RA2   |                 | - Repàs del tema 1 abans de l'examen: consultes amb JOIN, subconsultes i agregats   | 2     |
      | Bases de dades | ##+3 days##%Y-%m-%d##  | RA2   | Futura 3        | Pla F3                                                                              | 2     |
      | Bases de dades | ##+4 days##%Y-%m-%d##  | RA2   | Futura 4        | Pla F4                                                                              | 2     |
      | Bases de dades | ##+5 days##%Y-%m-%d##  | RA2   | Futura 5        | Pla F5                                                                              | 2     |
      | Bases de dades | ##+6 days##%Y-%m-%d##  | RA3   | Futura 6        | Pla F6                                                                              | 2     |
    And the following "mod_classprogress > entries" exist:
      | planitem    | content                                                                                          | user     |
      | Presentació | Normes explicades a tota la classe i prova diagnòstica de vint minuts, corregida a l'aula mateix | teacher1 |
    And I am on the "Bases de dades" "classprogress activity" page logged in as "teacher1"
    Then I should see "Primer programa" in the "[data-region='log-upcoming']" "css_element"
    And I should not see "Hola, món" in the "[data-region='log-upcoming']" "css_element"
    When I click on "Primer programa" "text" in the "[data-region='log-upcoming']" "css_element"
    Then I should see "Hola, món" in the "Primer programa" "table_row"
    And I should see "1.5 h · RA2" in the "Primer programa" "table_row"
    When I click on "Primer programa" "text" in the "[data-region='log-upcoming']" "css_element"
    Then I should not see "Hola, món" in the "[data-region='log-upcoming']" "css_element"
    And I should see "Repàs del tema 1 abans de l'examen" in the "[data-region='log-upcoming']" "css_element"
    And I should not see "agregats" in the "[data-region='log-upcoming']" "css_element"
    And I should see "Normes explicades a tota la classe" in the "Presentació" "table_row"
    And I should not see "corregida a l'aula mateix" in the "Presentació" "table_row"
    And I should not see "Normes de l'aula" in the "Presentació" "table_row"
    When I click on "Presentació" "text" in the "[data-region='log-logged']" "css_element"
    Then I should see "Normes de l'aula" in the "Presentació" "table_row"
    And I should not see "Futura 6"
    When I click on "All upcoming sessions (6)" "text"
    Then I should see "Futura 6" in the "[data-region='log-upcoming-more']" "css_element"
    And the page should meet accessibility standards

  # At 390 px the page must not scroll sideways in English or in Catalan (REQ-UIX-004). The Catalan run is not
  # automated: neither the development sites nor CI have the Catalan language pack, so it is a manual check of review
  # round R6 on the lab.
  @javascript @accessibility @REQ-UIX-004
  Scenario: At phone width the log page and the entry form fit the screen, with the block under the title and the late badge under the date
    Given the following "mod_classprogress > plan items" exist:
      | plan           | sessiondate             | block                                                              | title                                         | content          | hours |
      | Bases de dades | ##-40 days##%Y-%m-%d##  | Bloc A · Els elements de les bases de dades i el seu disseny (RA1) | Presentació del mòdul i de les normes d'aula  | Normes de l'aula | 2     |
      | Bases de dades | ##-38 days##%Y-%m-%d##  | Bloc A · Els elements de les bases de dades i el seu disseny (RA1) | Entorn de treball i instal·lació del servidor | Instal·lació     | 2     |
      | Bases de dades | ##-2 days##%Y-%m-%d##   | Bloc B · Ronda 1 · La botiga bàsica (RA2–RA4)                      | De l'E/R al relacional                        | Pas a taules     | 2     |
      | Bases de dades | ##today##%Y-%m-%d##     | Bloc B · Ronda 1 · La botiga bàsica (RA2–RA4)                      | Posem en marxa el servidor de bases de dades  | MariaDB          | 2     |
      | Bases de dades | ##+3 days##%Y-%m-%d##   | Bloc B · Ronda 1 · La botiga bàsica (RA2–RA4)                      | BotigaDB al servidor, i el seu plànol         | Diagrama         | 2     |
    And the following "mod_classprogress > entries" exist:
      | planitem                                      | content                    | writtenon           | user     |
      | Presentació del mòdul i de les normes d'aula  | Normes explicades a classe | ##today##%Y-%m-%d## | teacher1 |
      | Entorn de treball i instal·lació del servidor | IDE instal·lat a tothom    | ##today##%Y-%m-%d## | teacher1 |
    And I use a phone screen "390" px wide
    And I am on the "Bases de dades" "classprogress activity" page logged in as "teacher1"
    Then the page should not scroll sideways
    And I should see "Late by 40 days" in the "Presentació del mòdul i de les normes d'aula" "table_row"
    And I should see "Bloc A · Els elements" in the "Presentació del mòdul i de les normes d'aula" "table_row"
    And I should not see "Written on" in the "[data-region='log-logged']" "css_element"
    And the page should meet accessibility standards
    When I click on "Log" "link" in the "De l'E/R al relacional" "table_row"
    Then the page should not scroll sideways
    And the page should meet accessibility standards
