@iplus @report @report_comments
Feature: Comments report
  In order to understand what is going on in my Moodle site
  I need to be able to see where comments are made

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
      | Course 2 | C2 | 0 |
    Given the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | T1 | Teacher1 |
      | teacher2 | T2 | Teacher2 |
      | student1 | S1 | Student1 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | teacher1 | C2 | editingteacher |
      | teacher2 | C2 | editingteacher |
      | student1 | C1 | student |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add the "Comments" block
    And I add "comment 01" comment to comments block
    And I am on site homepage
    And I follow "Course 2"
    And I add the "Comments" block
    And I add "comment 02" comment to comments block
    And I add "comment 03" comment to comments block
    And I log out
 
  @javascript
  Scenario: See if there are links created on the commnet report.
    Given I log in as "teacher2"
    And I follow "Course 2"
    And I navigate to "Comments" node in "Course administration > Reports"
    Then I should not see "comment 01"
    And I should see "comment 02"
    And I follow "comment 02"
    Then I should see "Course 2"
    And I navigate to "Comments" node in "Course administration > Reports"
    And I follow "T1 Teacher1"
    Then I should see "comment 01"
    And I follow "Delete"
    And I click on "Delete" "button"
    Then I should see "comment 02"
    And I log out