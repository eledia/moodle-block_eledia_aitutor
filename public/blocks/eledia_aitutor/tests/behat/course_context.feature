@block @block_eledia_aitutor
Feature: eLeDia.ai Tutor course context gate
  In order to avoid exposing course context on pages where the tutor is not enabled
  As a site administrator
  I need the course-scoped standalone page to require an active tutor block in the course

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
      | ragserverurl      | https://rag.example.com/mcp | block_eledia_aitutor |
      | mcpserviceid      | 1                           | block_eledia_aitutor |
      | enablecoursechat  | 1                           | block_eledia_aitutor |
      | enableglobalchat  | 1                           | block_eledia_aitutor |

  @javascript
  Scenario: Course-scoped standalone chat is blocked until the tutor block is present
    Given I log in as "student1"
    When I visit "/blocks/eledia_aitutor/view.php?courseid=2"
    Then I should see "The tutor is not enabled in this course."
    And ".eledia_aitutor-page" "css_element" should not exist

  @javascript
  Scenario: Course-scoped standalone chat opens when the tutor block is present
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "eLeDia.ai Tutor" block
    And I log out
    And I log in as "student1"
    When I visit "/blocks/eledia_aitutor/view.php?courseid=2"
    Then I should see "Before you use the tutor for the first time"
    And ".eledia_aitutor-page" "css_element" should exist
