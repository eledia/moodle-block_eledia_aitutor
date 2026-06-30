@block @block_eledia_aitutor
Feature: Standalone tutor chat page
  In order to use the tutor from the Moodle App or as a focused full page
  As a learner
  I need the chat available on its own page outside any block

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | student1 | Sam       | Student  | student1@test.com |
    And the following config values are set as admin:
      | ragserverurl | https://rag.example.com/mcp | block_eledia_aitutor |
      | mcpserviceid | 1                           | block_eledia_aitutor |

  @javascript
  Scenario: Open the standalone chat page for global chat
    Given I log in as "student1"
    When I visit "/blocks/eledia_aitutor/view.php"
    Then I should see "eLeDia.ai Tutor"
    And I should see "Before you use the tutor for the first time"

  @javascript
  Scenario: Global chat page respects the site toggle
    Given the following config values are set as admin:
      | enableglobalchat | 0 | block_eledia_aitutor |
    And I log in as "student1"
    When I visit "/blocks/eledia_aitutor/view.php"
    Then I should see "Global chat is disabled on this site."

  @javascript
  Scenario: Prompt starters from the site setting appear under the welcome message
    Given the following config values are set as admin:
      | promptstarters | What's due this week? | block_eledia_aitutor |
    And I log in as "student1"
    When I visit "/blocks/eledia_aitutor/view.php"
    Then "What's due this week?" "button" should exist
