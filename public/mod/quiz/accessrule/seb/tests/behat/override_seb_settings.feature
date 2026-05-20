@javascript @mod_quiz @quizaccess @quizaccess_seb

Feature: Safe Exam Browser override settings

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher  | Teacher   | One      | teacher@example.com  |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher  | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity   | name      | course | idnumber |
      | quiz       | Test quiz | C1     | quiz1    |

  Scenario: Safe Exam Browser override is not in effect by default in the override settings
    Given I am on the "Test quiz" "mod_quiz > View" page logged in as "teacher"
    And I navigate to "Overrides" in current page administration
    And I press "Add user override"
    And I expand all fieldsets
    # Check SEB override is off by default
    Then I should see "Safe Exam Browser"
    And I should not see "Require the use of Safe Exam Browser"
    And I should not see "Show Safe Exam Browser download button"
    And I should not see "Show Exit Safe Exam Browser button, configured with this quit link"
    And I should not see "Ask user to confirm quitting"
    And I should not see "Enable quitting of SEB"
    And I should not see "Quit password"
    And I should not see "Enable reload in exam"
    And I should not see "Show SEB task bar"
    And I should not see "Show reload button"
    And I should not see "Show time"
    And I should not see "Show keyboard layout"
    And I should not see "Show Wi-Fi control"
    And I should not see "Enable audio controls"
    And I should not see "Allow browser access to camera"
    And I should not see "Allow browser access to microphone"
    And I should not see "Enable spell checking"
    And I should not see "Enable URL filtering"
    And I should not see "Allowed browser exam keys"

  Scenario: Safe Exam Browser is not required by default when enabled in the override settings
    Given I am on the "Test quiz" "mod_quiz > View" page logged in as "teacher"
    And I navigate to "Overrides" in current page administration
    And I press "Add user override"
    And I expand all fieldsets
    # Check fields for when "Require the use of Safe Exam Browser" is "No" (default).
    When I set the following fields to these values:
      | Override user       | Student One (student1@example.com) |
      | Enable SEB override | Yes                                |
    Then I should see "Require the use of Safe Exam Browser"
    And the field "Require the use of Safe Exam Browser" matches value "No"
    And I should not see "Show Safe Exam Browser download button"
    And I should not see "Show Exit Safe Exam Browser button, configured with this quit link"
    And I should not see "Ask user to confirm quitting"
    And I should not see "Enable quitting of SEB"
    And I should not see "Quit password"
    And I should not see "Enable reload in exam"
    And I should not see "Show SEB task bar"
    And I should not see "Show reload button"
    And I should not see "Show time"
    And I should not see "Show keyboard layout"
    And I should not see "Show Wi-Fi control"
    And I should not see "Enable audio controls"
    And I should not see "Allow browser access to camera"
    And I should not see "Allow browser access to microphone"
    And I should not see "Enable spell checking"
    And I should not see "Enable URL filtering"
    And I should not see "Allowed browser exam keys"
    # Check override table is displaying SEB settings.
    And I press "Save"
    And I should see "No" in the "Student One" "table_row"
    # Check edit page retains SEB settings for user.
    And I click on "Edit" "link" in the "Student One" "table_row"
    And I expand all fieldsets
    And I should see "No"

  Scenario: Safe Exam Browser shows correct settings when enabled and set to 'Yes – Configure manually'
    Given I am on the "Test quiz" "mod_quiz > View" page logged in as "teacher"
    And I navigate to "Overrides" in current page administration
    And I press "Add user override"
    And I expand all fieldsets
    And I set the following fields to these values:
      | Override user       | Student One (student1@example.com) |
      | Enable SEB override | Yes                                |
    # Check fields for when "Require the use of Safe Exam Browser" is "Yes – Configure manually".
    When I set the field "Require the use of Safe Exam Browser" to "Yes – Configure manually"
    Then I should see "Show Safe Exam Browser download button"
    And I should see "Show Exit Safe Exam Browser button, configured with this quit link"
    And I should see "Ask user to confirm quitting"
    And I should see "Enable quitting of SEB"
    And I should see "Quit password"
    And I should see "Enable reload in exam"
    And I should see "Show SEB task bar"
    And I should see "Show reload button"
    And I should see "Show time"
    And I should see "Show keyboard layout"
    And I should see "Show Wi-Fi control"
    And I should see "Enable audio controls"
    And I should see "Allow browser access to camera"
    And I should see "Allow browser access to microphone"
    And I should see "Enable spell checking"
    And I should see "Enable URL filtering"
    And I should not see "Allowed browser exam keys"
    # Check override table is displaying SEB settings.
    And I press "Save"
    And I should see "Yes – Configure manually" in the "Student One" "table_row"
    # Check edit page retains SEB settings for user.
    And I click on "Edit" "link" in the "Student One" "table_row"
    And I expand all fieldsets
    And I should see "Yes – Configure manually"

  Scenario: Safe Exam Browser shows correct settings when enabled and set to 'Yes – Use SEB client config'
    Given I am on the "Test quiz" "mod_quiz > View" page logged in as "teacher"
    And I navigate to "Overrides" in current page administration
    And I press "Add user override"
    And I expand all fieldsets
    And I set the following fields to these values:
      | Override user       | Student One (student1@example.com) |
      | Enable SEB override | Yes                                |
    # Check fields for when "Require the use of Safe Exam Browser" is "Yes – Use SEB client config".
    When I set the field "Require the use of Safe Exam Browser" to "Yes – Use SEB client config"
    Then I should see "Show Safe Exam Browser download button"
    And I should see "Allowed browser exam keys"
    And I should not see "Show Exit Safe Exam Browser button, configured with this quit link"
    And I should not see "Ask user to confirm quitting"
    And I should not see "Enable quitting of SEB"
    And I should not see "Quit password"
    And I should not see "Enable reload in exam"
    And I should not see "Show SEB task bar"
    And I should not see "Show reload button"
    And I should not see "Show time"
    And I should not see "Show keyboard layout"
    And I should not see "Show Wi-Fi control"
    And I should not see "Enable audio controls"
    And I should not see "Allow browser access to camera"
    And I should not see "Allow browser access to microphone"
    And I should not see "Enable spell checking"
    And I should not see "Enable URL filtering"
    # Check override table is displaying SEB settings.
    And I press "Save"
    And I should see "Yes – Use SEB client config" in the "Student One" "table_row"
    # Check edit page retains SEB settings for user.
    And I click on "Edit" "link" in the "Student One" "table_row"
    And I expand all fieldsets
    And I should see "Yes – Use SEB client config"

  Scenario: Safe Exam Browser validates correctly in the quiz override settings
    Given I am on the "Test quiz" "mod_quiz > View" page logged in as "teacher"
    And I navigate to "Overrides" in current page administration
    When I press "Add user override"
    And I expand all fieldsets
    And I set the field "Override user" to "Student One (student1@example.com)"
    And I press "Save"
    Then I should see "You must override at least one of the quiz settings."
    And I expand all fieldsets
    And I set the field "Enable SEB override" to "Yes"
    And I press "Save"
    And I should see "User overrides"
    And I should see "Require the use of Safe Exam Browser" in the "Student One" "table_row"
    And I click on "Edit" "link" in the "Student One" "table_row"
    And I expand all fieldsets
    And I set the field "Enable SEB override" to "No"
    And I press "Save"
    And I should see "You must override at least one of the quiz settings."
    And I set the field "Require password" to "password"
    And I press "Save"
    And I should see "User overrides"
    And I should see "Require password" in the "Student One" "table_row"
    And I should not see "Require the use of Safe Exam Browser" in the "Student One" "table_row"

  Scenario: Safe Exam Browser override affects targeted users
    Given I am on the "Test quiz" "mod_quiz > View" page logged in as "teacher"
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student2 | Student   | Two      | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student2 | C1     | student        |
    And I navigate to "Overrides" in current page administration
    When I press "Add user override"
    And I set the field "Override user" to "Student One (student1@example.com)"
    And I expand all fieldsets
    And I set the following fields to these values:
      | Enable SEB override                  | Yes                                |
      | Require the use of Safe Exam Browser | Yes – Configure manually           |
    And I press "Save"
    And I am on the "Test quiz" "mod_quiz > View" page logged in as "student1"
    Then I should see "This quiz has been configured so that students may only attempt it using the Safe Exam Browser."
    And I am on the "Test quiz" "mod_quiz > View" page logged in as "student2"
    And I should not see "This quiz has been configured so that students may only attempt it using the Safe Exam Browser."

  Scenario: Safe Exam Browser override option "Use SEB client config" is not available for users without permission
    Given the following "permission overrides" exist:
      | capability                                   | permission | role           | contextlevel | reference |
      | quizaccess/seb:manage_seb_usesebclientconfig | Prohibit   | editingteacher | System       |           |
    And the following "quizaccess_seb > seb templates" exist:
      | name       |
      | Template 1 |
    When I am on the "Test quiz" "mod_quiz > View" page logged in as "teacher"
    And I navigate to "Overrides" in current page administration
    And I press "Add user override"
    And I expand all fieldsets
    And I set the field "Enable SEB override" to "Yes"
    Then the "Require the use of Safe Exam Browser" select box should not contain "Yes – Use SEB client config"
    And the "Require the use of Safe Exam Browser" select box should contain "Yes – Configure manually"
    And the "Require the use of Safe Exam Browser" select box should contain "Yes – Use an existing template"
    And the "Require the use of Safe Exam Browser" select box should contain "No"

  Scenario: Safe Exam Browser override option "Yes – Configure manually" is not available for users without permission
    Given the following "permission overrides" exist:
      | capability                                   | permission | role           | contextlevel | reference |
      | quizaccess/seb:manage_seb_configuremanually  | Prohibit   | editingteacher | System       |           |
    And the following "quizaccess_seb > seb templates" exist:
      | name       |
      | Template 1 |
    When I am on the "Test quiz" "mod_quiz > View" page logged in as "teacher"
    And I navigate to "Overrides" in current page administration
    And I press "Add user override"
    And I expand all fieldsets
    And I set the field "Enable SEB override" to "Yes"
    Then the "Require the use of Safe Exam Browser" select box should not contain "Yes – Configure manually"
    And the "Require the use of Safe Exam Browser" select box should contain "Yes – Use SEB client config"
    And the "Require the use of Safe Exam Browser" select box should contain "Yes – Use an existing template"
    And the "Require the use of Safe Exam Browser" select box should contain "No"

  Scenario: Safe Exam Browser override option "Yes – Use an existing template" is not available for users without permission
    Given the following "permission overrides" exist:
      | capability                           | permission | role           | contextlevel | reference |
      | quizaccess/seb:manage_seb_templateid | Prohibit   | editingteacher | System       |           |
    And the following "quizaccess_seb > seb templates" exist:
      | name       |
      | Template 1 |
    When I am on the "Test quiz" "mod_quiz > View" page logged in as "teacher"
    And I navigate to "Overrides" in current page administration
    And I press "Add user override"
    And I expand all fieldsets
    And I set the field "Enable SEB override" to "Yes"
    Then the "Require the use of Safe Exam Browser" select box should not contain "Yes – Use an existing template"
    And the "Require the use of Safe Exam Browser" select box should contain "Yes – Configure manually"
    And the "Require the use of Safe Exam Browser" select box should contain "Yes – Use SEB client config"
    And the "Require the use of Safe Exam Browser" select box should contain "No"

  Scenario: Safe Exam Browser override option "No" is not available for users without permission
    Given the following "permission overrides" exist:
      | capability                                            | permission | role           | contextlevel | reference |
      | quizaccess/seb:manage_seb_donotrequiresafeexambrowser | Prohibit   | editingteacher | System       |           |
    And the following "quizaccess_seb > seb templates" exist:
      | name       |
      | Template 1 |
    When I am on the "Test quiz" "mod_quiz > View" page logged in as "teacher"
    And I navigate to "Overrides" in current page administration
    And I press "Add user override"
    And I expand all fieldsets
    And I set the field "Enable SEB override" to "Yes"
    Then the "Require the use of Safe Exam Browser" select box should not contain "No"
    And the "Require the use of Safe Exam Browser" select box should contain "Yes – Use an existing template"
    And the "Require the use of Safe Exam Browser" select box should contain "Yes – Configure manually"
    And the "Require the use of Safe Exam Browser" select box should contain "Yes – Use SEB client config"

  Scenario: Teachers can't override quizzes with manual SEB settings in the override view without permission
    Given the following "permission overrides" exist:
      | capability                                           | permission | role           | contextlevel | reference |
      | quizaccess/seb:manage_seb_configuremanually          | Allow      | editingteacher | System       |           |
      | quizaccess/seb:manage_seb_configuremanually_override | Prohibit   | editingteacher | System       |           |
    And the following "quizaccess_seb > seb templates" exist:
      | name       |
      | Template 1 |
    When I am on the "Test quiz" "quiz activity editing" page logged in as admin
    And I expand all fieldsets
    And I set the field "Require the use of Safe Exam Browser" to "Yes – Configure manually"
    And I set the field "Safe Exam Browser config template" to "Template 1"
    And I press "Save and return to course"
    And I am on the "Test quiz" "mod_quiz > View" page logged in as "teacher"
    And I navigate to "Overrides" in current page administration
    And I press "Add user override"
    And I expand all fieldsets
    Then I should not see "Enable SEB override"
    And I should see "Yes – Configure manually" in the "#fitem_id_require_seb_static" "css_element"

  Scenario: Teachers can't override quizzes using client SEB settings in the override view without permission
    Given the following "permission overrides" exist:
      | capability                                            | permission | role           | contextlevel | reference |
      | quizaccess/seb:manage_seb_usesebclientconfig          | Allow      | editingteacher | System       |           |
      | quizaccess/seb:manage_seb_usesebclientconfig_override | Prohibit   | editingteacher | System       |           |
    And the following "quizaccess_seb > seb templates" exist:
      | name       |
      | Template 1 |
    When I am on the "Test quiz" "quiz activity editing" page logged in as admin
    And I expand all fieldsets
    And I set the field "Require the use of Safe Exam Browser" to "Yes – Use SEB client config"
    And I set the field "Safe Exam Browser config template" to "Template 1"
    And I press "Save and return to course"
    And I am on the "Test quiz" "mod_quiz > View" page logged in as "teacher"
    And I navigate to "Overrides" in current page administration
    And I press "Add user override"
    And I expand all fieldsets
    Then I should not see "Enable SEB override"
    And I should see "Yes – Use SEB client config" in the "#fitem_id_require_seb_static" "css_element"

  Scenario: Teachers can't override quizzes with no SEB settings in the override view without permission
    Given the following "permission overrides" exist:
      | capability                                                     | permission | role           | contextlevel | reference |
      | quizaccess/seb:manage_seb_donotrequiresafeexambrowser          | Allow      | editingteacher | System       |           |
      | quizaccess/seb:manage_seb_donotrequiresafeexambrowser_override | Prohibit   | editingteacher | System       |           |
    And the following "quizaccess_seb > seb templates" exist:
      | name       |
      | Template 1 |
    When I am on the "Test quiz" "mod_quiz > View" page logged in as "teacher"
    And I navigate to "Overrides" in current page administration
    And I press "Add user override"
    And I expand all fieldsets
    Then I should not see "Enable SEB override"
    And I should see "No" in the "#fitem_id_require_seb_static" "css_element"

  Scenario: Teachers can't override quizzes using SEB template without permission to set SEB template in the override view
    Given the following "permission overrides" exist:
      | capability                                    | permission | role           | contextlevel | reference |
      | quizaccess/seb:manage_seb_templateid          | Allow      | editingteacher | System       |           |
      | quizaccess/seb:manage_seb_templateid_override | Prohibit   | editingteacher | System       |           |
    And the following "quizaccess_seb > seb templates" exist:
      | name       |
      | Template 1 |
    When I am on the "Test quiz" "quiz activity editing" page logged in as admin
    And I expand all fieldsets
    And I set the field "Require the use of Safe Exam Browser" to "Yes – Use an existing template"
    And I set the field "Safe Exam Browser config template" to "Template 1"
    And I press "Save and return to course"
    And I am on the "Test quiz" "mod_quiz > View" page logged in as "teacher"
    And I navigate to "Overrides" in current page administration
    And I press "Add user override"
    And I expand all fieldsets
    Then I should not see "Enable SEB override"
    And I should see "Yes – Use an existing template" in the "#fitem_id_require_seb_static" "css_element"

  @_file_upload
  Scenario: Teachers can't override quizzes with uploaded SEB settings in the override view without permission
    Given the following "permission overrides" exist:
      | capability                                               | permission | role           | contextlevel | reference |
      | quizaccess/seb:manage_filemanager_sebconfigfile          | Allow      | editingteacher | System       |           |
      | quizaccess/seb:manage_filemanager_sebconfigfile_override | Prohibit   | editingteacher | System       |           |
    When I am on the "Test quiz" "quiz activity editing" page logged in as admin
    And I expand all fieldsets
    And I set the field "Require the use of Safe Exam Browser" to "Yes – Upload my own config"
    And I upload "mod/quiz/accessrule/seb/tests/fixtures/unencrypted.seb" file to "Upload Safe Exam Browser config file" filemanager
    And I press "Save and return to course"
    And I am on the "Test quiz" "mod_quiz > View" page logged in as "teacher"
    And I navigate to "Overrides" in current page administration
    And I press "Add user override"
    And I expand all fieldsets
    Then I should not see "Enable SEB override"
    And I should see "Yes – Upload my own config" in the "#fitem_id_require_seb_static" "css_element"
