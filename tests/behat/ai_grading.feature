@local @local_forumia
Feature: AI grading of forum participation
  In order to grade forum participation with AI under my control
  As a teacher
  I need to choose the grading mode and review the AI's evaluations

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name           | course | idnumber | grade_forum |
      | forum    | Graded forum   | C1     | forum1   | 10          |
      | forum    | Ungraded forum | C1     | forum2   | 0           |

  Scenario: A teacher switches on automatic AI grading
    Given I am on the "Graded forum" "forum activity" page logged in as "teacher1"
    And I navigate to "Forumia" in current page administration
    When I set the following fields to these values:
      | AI grading                        | Automatic: apply the grade to students who have no grade yet |
      | Hours before evaluating a student | 24                                                           |
    And I press "Save Forumia settings"
    Then I should see "Forumia settings saved successfully."
    And the field "AI grading" matches value "Automatic: apply the grade to students who have no grade yet"
    And the field "Hours before evaluating a student" matches value "24"

  Scenario: AI grading cannot be enabled in a forum without whole-forum grading
    Given I am on the "Ungraded forum" "forum activity" page logged in as "teacher1"
    And I navigate to "Forumia" in current page administration
    When I set the field "AI grading" to "Suggest: a teacher confirms each grade"
    And I press "Save Forumia settings"
    Then I should see "AI grading needs whole-forum grading with a point maximum"

  Scenario: A teacher accepts all AI grade suggestions at once
    Given the following "local_forumia > evaluations" exist:
      | forum  | user     | grade | rationale                       | postcount |
      | forum1 | student1 | 7     | Strong original post, brief replies. | 3    |
    And I am on the "Graded forum" "forum activity" page logged in as "teacher1"
    When I navigate to "Forumia: AI grade suggestions" in current page administration
    Then I should see "Student One"
    And I should see "Strong original post, brief replies."
    And I should see "7 / 10"
    And I should see "Not graded"
    And I press "Accept all suggestions"
    And I should see "Apply every pending suggestion"
    And I press "Continue"
    And I should see "Grades applied: 1. Could not be applied: 0."
    And I should see "There are no AI evaluations waiting for review in this forum."

  Scenario: A teacher discards a suggestion
    Given the following "local_forumia > evaluations" exist:
      | forum  | user     | grade | rationale      |
      | forum1 | student1 | 4     | Off topic.     |
    And I am on the "Graded forum" "forum activity" page logged in as "teacher1"
    When I navigate to "Forumia: AI grade suggestions" in current page administration
    And I press "Discard"
    Then I should see "The suggestion has been discarded. No grade was changed."
    And I should see "There are no AI evaluations waiting for review in this forum."
