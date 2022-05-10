@ewallah @report @report_comments @javascript
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
    And the following "activities" exist:
      | activity           | name               | intro   | course   | idnumber    | section |
      | wiki               | Test wiki          | Test l  | C2       | wiki1       | 1       |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | teacher1 | C2 | editingteacher |
      | teacher2 | C2 | editingteacher |
      | student1 | C1 | student |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Comments" block
    And I add "comment 01" comment to comments block
    And I am on "Course 2" course homepage
    And I add the "Comments" block
    And I add "comment 02" comment to comments block
    And I add "comment 03" comment to comments block
    And I am on "Course 2" course homepage
    And I follow "Test wiki"
    And I press "Create page"
    And I set the following fields to these values:
      | HTML format | Test wiki content |
      | Tags | Test tag 1, Test tag 2, |
    And I press "Save"
    And I select "Comments" from the "jump" singleselect
    And I follow "Add comment"
    And I set the following fields to these values:
      | Comment | comment 04 |
    And I press "Save changes"
    And I log out

  Scenario: See if there are links created on the comment report.
    When I am on the "C2" "Course" page logged in as "teacher2"
    And I navigate to "Reports > Comments" in current page administration
    Then I should not see "comment 01"
    And I should see "comment 02"
    And I should see "comment 03"
    And I should see "comment 04"
    And I follow "comment 04"
    Then I should see "Test wiki"
    And I am on "Course 2" course homepage
    And I navigate to "Reports > Comments" in current page administration
    And I follow "comment 02"
    Then I should see "Course 2"
    And I am on "Course 2" course homepage
    And I navigate to "Reports > Comments" in current page administration
    And I follow "T1 Teacher1"
    Then I should see "comment 01"
    And I follow "Delete"
    And I click on "Delete" "button"
    Then I should see "comment 02"

  Scenario: See comments as a student.
    When I am on the "C1" "Course" page logged in as "student1"
    Then I should see "comment 01"
    And I should not see "comment 02"
