@block @block_elediaaitutor
Feature: eLeDia.ai Tutor chat block UI
  In order to get help while learning
  As a student
  I need a polished, accessible tutor chat embedded in Moodle

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | student1 | Sam       | Student  | student1@test.com |
      | teacher1 | Tess      | Teacher  | teacher1@test.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And the following config values are set as admin:
      | ragserverurl       | https://rag.example.com/mcp | block_elediaaitutor |
      | mcpserviceid       | 1                           | block_elediaaitutor |
      | defaultdisplaymode | embedded                    | block_elediaaitutor |

  Scenario: The embedded chat shell renders with its composer and welcome message
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    When I add the "eLeDia.ai Tutor" block
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "eLeDia.ai Tutor"
    And "form[data-region=composer]" "css_element" should exist
    And "textarea[data-region=input]" "css_element" should exist
    And ".elediaaitutor-log[role=log]" "css_element" should exist

  Scenario: Learners are offered the pedagogical answer styles
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "eLeDia.ai Tutor" block
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "Explain" in the ".elediaaitutor-styles" "css_element"
    And I should see "Hints only" in the ".elediaaitutor-styles" "css_element"
    And I should see "Quiz me" in the ".elediaaitutor-styles" "css_element"

  Scenario: A misconfigured connector shows an admin-facing error to managers
    Given the following config values are set as admin:
      | mcpserviceid | 0 | block_elediaaitutor |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    When I add the "eLeDia.ai Tutor" block
    Then I should see "configuration problem" in the ".elediaaitutor-unavailable" "css_element"

  @javascript
  Scenario: The chat can be opened as a modal and closed again
    Given the following config values are set as admin:
      | defaultdisplaymode | modal | block_elediaaitutor |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "eLeDia.ai Tutor" block
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I click on "[data-action=launch]" "css_element"
    Then ".elediaaitutor-modal" "css_element" should be visible
    And "textarea[data-region=input]" "css_element" should be visible
    When I click on "[data-action=close]" "css_element"
    Then ".elediaaitutor-modal" "css_element" should not be visible

  @javascript
  Scenario: The chat can be opened in full-screen mode
    Given the following config values are set as admin:
      | defaultdisplaymode | fullscreen | block_elediaaitutor |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "eLeDia.ai Tutor" block
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I click on "[data-action=launch]" "css_element"
    Then ".elediaaitutor-fullscreen" "css_element" should be visible
