@local @local_forumia
Feature: Configure the Forumia assistant on a forum
  In order to let an AI assistant support a discussion
  As a teacher
  I need to reach the Forumia settings from the forum and save them

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
      | forumbot | Course    | Assistant| forumbot@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    # Only an account designated by a site administrator can publish the
    # assistant's replies. Here it is the site default assistant account.
    And the following config values are set as admin:
      | defaultbot | forumbot | local_forumia |
    And the following "activities" exist:
      | activity | name        | course | idnumber |
      | forum    | Test forum  | C1     | forum1   |

  @javascript
  Scenario: A teacher reaches the Forumia settings from the forum
    Given I am on the "Test forum" "forum activity" page logged in as "teacher1"
    When I navigate to "Forumia" in current page administration
    Then I should see "Forumia"
    And I should see "Enable Forumia in this forum"
    And I should see "Response mode"

  @javascript
  Scenario: A teacher enables the assistant and the settings persist
    Given I am on the "Test forum" "forum activity" page logged in as "teacher1"
    And I navigate to "Forumia" in current page administration
    When I set the following fields to these values:
      | Enable Forumia in this forum | 1                        |
      | AI assistant account         | Course Assistant (forumbot) |
      | Daily request limit for this forum | 25                 |
    And I press "Save Forumia settings"
    Then I should see "Forumia settings saved successfully."
    And I navigate to "Forumia" in current page administration
    And the field "Enable Forumia in this forum" matches value "1"
    And the field "Daily request limit for this forum" matches value "25"

  Scenario: Course staff are not offered as the assistant account
    Given I am on the "Test forum" "forum activity" page logged in as "teacher1"
    When I navigate to "Forumia" in current page administration
    Then the "AI assistant account" select box should contain "Course Assistant (forumbot)"
    And the "AI assistant account" select box should not contain "Teacher One (teacher1)"

  @javascript
  Scenario: Enabling the assistant without an account is rejected
    Given I am on the "Test forum" "forum activity" page logged in as "teacher1"
    And I navigate to "Forumia" in current page administration
    When I set the field "Enable Forumia in this forum" to "1"
    And I press "Save Forumia settings"
    Then I should see "No valid AI assistant account found"

  Scenario: A student cannot reach the Forumia settings
    When I log in as "student1"
    And I am on the "Test forum" "forum activity" page
    Then I should not see "Forumia"
