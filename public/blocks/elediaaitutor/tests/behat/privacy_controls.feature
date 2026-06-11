@block @block_elediaaitutor
Feature: Privacy guidelines, long-term memory opt-in and data deletion
  In order to stay in control of my data
  As a learner using the AI tutor
  I need privacy information, a memory opt-in and a way to delete my data

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
      | ragserverurl | https://rag.example.com/mcp | block_elediaaitutor |
      | mcpserviceid | 1                           | block_elediaaitutor |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "eLeDia.ai Tutor" block
    And I log out

  @javascript
  Scenario: View the privacy guidelines from the chat
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    When I click on "[data-action=privacy]" "css_element"
    Then I should see "AI answers can be wrong"
    And I should see "What is sent when you chat"
    And I should see "What is stored"
    And I should see "Long-term memory"
    And I should see "Deleting your data"
    And I should see "Delete all my tutor data"

  @javascript
  Scenario: Opt in to long-term memory and have the choice persist
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    When I click on "[data-action=privacy]" "css_element"
    And I set the field "Allow the tutor to remember information across conversations (long-term memory)" to "1"
    Then I should see "Preference saved."
    When I reload the page
    And I click on "[data-action=privacy]" "css_element"
    Then the field "Allow the tutor to remember information across conversations (long-term memory)" matches value "1"

  @javascript
  Scenario: Opt out of long-term memory again
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I click on "[data-action=privacy]" "css_element"
    And I set the field "Allow the tutor to remember information across conversations (long-term memory)" to "1"
    And I should see "Preference saved."
    When I set the field "Allow the tutor to remember information across conversations (long-term memory)" to ""
    And I reload the page
    And I click on "[data-action=privacy]" "css_element"
    Then the field "Allow the tutor to remember information across conversations (long-term memory)" matches value ""

  @javascript
  Scenario: Request deletion of all tutor data
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    When I click on "[data-action=privacy]" "css_element"
    And I click on "Delete all my tutor data" "button"
    And I click on "Yes, delete everything" "button"
    Then I should see "0 conversation(s) deleted."
    And I should see "does not support remote deletion"

  @javascript
  Scenario: First use requires acknowledging the privacy guidelines
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "Before you use the tutor for the first time"
    And the "Your message to the tutor" "field" should be disabled
    And the "Agree and start" "button" should be disabled
    When I set the field "I acknowledge the privacy guidelines." to "1"
    And I click on "Agree and start" "button"
    Then I should not see "Before you use the tutor for the first time"
    And the "Your message to the tutor" "field" should be enabled
    When I reload the page
    Then I should not see "Before you use the tutor for the first time"

  @javascript
  Scenario: Admin-defined privacy guidelines text replaces the default sections
    Given the following config values are set as admin:
      | privacyguidelinestext | <p>Institution privacy statement ABC.</p> | block_elediaaitutor |
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I click on "[data-action=privacy]" "css_element"
    Then I should see "Institution privacy statement ABC."
    And I should not see "What is sent when you chat"
    And I should see "Long-term memory"
    And I should see "Delete all my tutor data"
