@javascript @mod_quiz @quizaccess @quizaccess_seb

Feature: Safe Exam Browser override settings

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher  | Teacher   | One      | teacher@example.com  |
      | student1 | Student   | One      | student1@example.com |
      | student2 | Student   | Two      | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher  | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "activities" exist:
      | activity   | name      | course | idnumber |
      | quiz       | Test quiz | C1     | quiz1    |

  Scenario: Safe Exam Browser fields correctly display in the override settings
    Given I am on the "Test quiz" "mod_quiz > View" page logged in as "teacher"
    And I change window size to "large"
    And I navigate to "Overrides" in current page administration
    And I press "Add user override"
    And I expand all fieldsets
    Then I should see "Safe Exam Browser"
    Then I should not see "Require the use of Safe Exam Browser"
    And I set the following fields to these values:
      | Override user       | Student One (student1@example.com) |
      | Enable SEB override | Yes                                |
    # Check fields for when "Require the use of Safe Exam Browser" is "No" (default).
    Then I should see "Require the use of Safe Exam Browser"
    Then I should not see "Show Safe Exam Browser download button"
    Then I should not see "Show Exit Safe Exam Browser button, configured with this quit link"
    Then I should not see "Ask user to confirm quitting"
    Then I should not see "Enable quitting of SEB"
    Then I should not see "Quit password"
    Then I should not see "Enable reload in exam"
    Then I should not see "Show SEB task bar"
    Then I should not see "Show reload button"
    Then I should not see "Show time"
    Then I should not see "Show keyboard layout"
    Then I should not see "Show Wi-Fi control"
    Then I should not see "Enable audio controls"
    Then I should not see "Allow browser access to camera"
    Then I should not see "Allow browser access to microphone"
    Then I should not see "Enable spell checking"
    Then I should not see "Enable URL filtering"
    Then I should not see "Allowed browser exam keys"
    # Check fields for when "Require the use of Safe Exam Browser" is "Yes – Configure manually".
    And I set the field "Require the use of Safe Exam Browser" to "Yes – Configure manually"
    Then I should see "Show Safe Exam Browser download button"
    Then I should see "Show Exit Safe Exam Browser button, configured with this quit link"
    Then I should see "Ask user to confirm quitting"
    Then I should see "Enable quitting of SEB"
    Then I should see "Quit password"
    Then I should see "Enable reload in exam"
    Then I should see "Show SEB task bar"
    Then I should see "Show reload button"
    Then I should see "Show time"
    Then I should see "Show keyboard layout"
    Then I should see "Show Wi-Fi control"
    Then I should see "Enable audio controls"
    Then I should see "Allow browser access to camera"
    Then I should see "Allow browser access to microphone"
    Then I should see "Enable spell checking"
    Then I should see "Enable URL filtering"
    Then I should not see "Allowed browser exam keys"
    # Check fields for when "Require the use of Safe Exam Browser" is "Yes – Use SEB client config".
    And I set the field "Require the use of Safe Exam Browser" to "Yes – Use SEB client config"
    Then I should see "Show Safe Exam Browser download button"
    Then I should see "Allowed browser exam keys"
    Then I should not see "Show Exit Safe Exam Browser button, configured with this quit link"
    Then I should not see "Ask user to confirm quitting"
    Then I should not see "Enable quitting of SEB"
    Then I should not see "Quit password"
    Then I should not see "Enable reload in exam"
    Then I should not see "Show SEB task bar"
    Then I should not see "Show reload button"
    Then I should not see "Show time"
    Then I should not see "Show keyboard layout"
    Then I should not see "Show Wi-Fi control"
    Then I should not see "Enable audio controls"
    Then I should not see "Allow browser access to camera"
    Then I should not see "Allow browser access to microphone"
    Then I should not see "Enable spell checking"
    Then I should not see "Enable URL filtering"
    # Check override table is displaying SEB settings.
    And I press "Save"
    Then I should see "Yes – Use SEB client config" in the "Student One" "table_row"
    # Check edit page retains SEB settings for user.
    And I click on "Edit" "link" in the "Student One" "table_row"
    And I expand all fieldsets
    Then I should see "Yes – Use SEB client config"

  Scenario: Safe Exam Browser validates correctly in the quiz override settings
    Given I am on the "Test quiz" "mod_quiz > View" page logged in as "teacher"
    And I change window size to "large"
    And I navigate to "Overrides" in current page administration
    And I press "Add user override"
    And I expand all fieldsets
    And I set the field "Override user" to "Student One (student1@example.com)"
    And I press "Save"
    Then I should see "You must override at least one of the quiz settings."
    And I expand all fieldsets
    And I set the field "Enable SEB override" to "Yes"
    And I press "Save"
    Then I should see "User overrides"
    Then I should see "Require the use of Safe Exam Browser" in the "Student One" "table_row"
    And I click on "Edit" "link" in the "Student One" "table_row"
    And I expand all fieldsets
    And I set the field "Enable SEB override" to "No"
    And I press "Save"
    Then I should see "You must override at least one of the quiz settings."
    And I set the field "Require password" to "password"
    And I press "Save"
    Then I should see "User overrides"
    Then I should see "Require password" in the "Student One" "table_row"
    Then I should not see "Require the use of Safe Exam Browser" in the "Student One" "table_row"

  Scenario: Safe Exam Browser override affects targeted users
    Given I am on the "Test quiz" "mod_quiz > View" page logged in as "teacher"
    And I change window size to "large"
    And I navigate to "Overrides" in current page administration
    And I press "Add user override"
    And I set the field "Override user" to "Student One (student1@example.com)"
    And I expand all fieldsets
    And I set the following fields to these values:
      | Enable SEB override                  | Yes                                |
      | Require the use of Safe Exam Browser | Yes – Configure manually           |
    And I press "Save"
    And I am on the "Test quiz" "mod_quiz > View" page logged in as "student1"
    Then I should see "This quiz has been configured so that students may only attempt it using the Safe Exam Browser."
    And I am on the "Test quiz" "mod_quiz > View" page logged in as "student2"
    Then I should not see "This quiz has been configured so that students may only attempt it using the Safe Exam Browser."
