@mod @mod_assign @javascript
Feature: Assignment grading notifications respect notification control settings
  In order to keep assignment grading notifications consistent
  As a teacher
  I need notification sending to follow allownotifycontrol and sendstudentnotifications.

  Background:
    Given the following config values are set as admin:
      | sendcoursewelcomemessage | 0 | enrol_manual |
      | allownotifycontrol       | 1 | assign       |
      | sendstudentnotifications | 1 | assign       |
      | submissionreceipts       | 0 | assign       |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activity" exists:
      | activity                            | assign   |
      | course                              | C1       |
      | name                                | Assign 1 |
      | submissiondrafts                    | 0        |
      | assignsubmission_onlinetext_enabled | 1        |
      | assignsubmission_file_enabled       | 0        |
      | markingworkflow                     | 1        |
      | sendstudentnotifications            | 1        |
    And the following "mod_assign > submissions" exist:
      | assign   | user     | onlinetext           |
      | Assign 1 | student1 | Student 1 submission |

  Scenario: Grading view should display Notify Student checkbox when allownotifycontrol is enabled
    When I am on the "Assign 1" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I click on "Grade actions" "actionmenu" in the "Student " "table_row"
    And I choose "Grade" in the open action menu
    Then "[data-region='grading-actions-form'] input[type='checkbox'][name='sendstudentnotifications']" "css_element" should exist

  Scenario: Grading view should not display Notify Student checkbox when allownotifycontrol is disabled
    Given the following config values are set as admin:
      | allownotifycontrol       | 0 | assign |
    When I am on the "Assign 1" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I click on "Grade actions" "actionmenu" in the "Student " "table_row"
    And I choose "Grade" in the open action menu
    Then "[data-region='grading-actions-form'] input[type='checkbox'][name='sendstudentnotifications']" "css_element" should not exist

  Scenario: Quick grading view should display Notify Students checkbox when allownotifycontrol is enabled
    When I am on the "Assign 1" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I click on "Quick grading" "checkbox"
    Then "[data-region='quick-grading-save'] input[type='checkbox'][name='sendstudentnotifications']" "css_element" should exist in the "sticky-footer" "region"

  Scenario: Quick grading view should not display Notify Students checkbox when allownotifycontrol is disabled
    Given the following config values are set as admin:
      | allownotifycontrol       | 0 | assign |
    When I am on the "Assign 1" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I click on "Quick grading" "checkbox"
    Then "[data-region='quick-grading-save'] input[type='checkbox'][name='sendstudentnotifications']" "css_element" should not exist in the "sticky-footer" "region"

  Scenario: Marking workflow view should display Notify Student checkbox when allownotifycontrol is enabled
    When I am on the "Assign 1" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I set the field "selectall" to "1"
    And I click on "Change marking state" "button" in the "sticky-footer" "region"
    And I click on "Change marking state" "button" in the ".modal-footer" "css_element"
    Then "select[name='sendstudentnotifications']" "css_element" should exist in the ".setworkflowstate" "css_element"

  Scenario: Marking workflow view should not display Notify Student checkbox when allownotifycontrol is disabled
    Given the following config values are set as admin:
      | allownotifycontrol       | 0 | assign |
    When I am on the "Assign 1" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I set the field "selectall" to "1"
    And I click on "Change marking state" "button" in the "sticky-footer" "region"
    And I click on "Change marking state" "button" in the ".modal-footer" "css_element"
    Then "select[name='sendstudentnotifications']" "css_element" should not exist in the ".setworkflowstate" "css_element"

  Scenario: Grading panel sends notification when allownotifycontrol is enabled
    When I am on the "Assign 1" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I click on "Grade actions" "actionmenu" in the "Student " "table_row"
    And I choose "Grade" in the open action menu
    And the "checked" attribute of "[data-region='grading-actions-form'] input[type='checkbox'][name='sendstudentnotifications']" "css_element" should contain "true"
    And I set the field "Grade out of 100" to "50"
    And I set the field "Marking workflow state" to "Released"
    And I press "Save changes"
    And I run the scheduled task "mod_assign\task\cron_task"
    And I log in as "student1"
    And I open the notification popover
    Then I should see "Assign 1" in the "#nav-notification-popover-container" "css_element"

  Scenario: Grading panel sends notification when control is disabled and sendstudentnotifications is enabled
    Given the following config values are set as admin:
      | allownotifycontrol       | 0 | assign |
      | sendstudentnotifications | 1 | assign |
    When I am on the "Assign 1" Activity page logged in as teacher1
    And I go to "Student 1" "Assign 1" activity advanced grading page
    And I set the field "Grade out of 100" to "50"
    And I set the field "Marking workflow state" to "Released"
    And I press "Save changes"
    And I run the scheduled task "mod_assign\task\cron_task"
    And I log in as "student1"
    And I open the notification popover
    Then I should see "Assign 1" in the "#nav-notification-popover-container" "css_element"

  Scenario: Grading panel does not send notification when control is disabled and sendstudentnotifications is disabled
    Given the following config values are set as admin:
      | allownotifycontrol       | 0 | assign |
      | sendstudentnotifications | 0 | assign |
    When I am on the "Assign 1" Activity page logged in as teacher1
    And I go to "Student 1" "Assign 1" activity advanced grading page
    And I set the field "Grade out of 100" to "50"
    And I set the field "Marking workflow state" to "Released"
    And I press "Save changes"
    And I run the scheduled task "mod_assign\task\cron_task"
    And I log in as "student1"
    And I open the notification popover
    Then I should not see "Assign 1" in the "#nav-notification-popover-container" "css_element"

  Scenario: Marking workflow release does not send notification when control is enabled and notify checkbox is not checked
    When I am on the "Assign 1" Activity page logged in as teacher1
    And I go to "Student 1" "Assign 1" activity advanced grading page
    And I set the field "Grade out of 100" to "50"
    And I set the field "Marking workflow state" to "In marking"
    And I set the field "Notify student" to "0"
    And I press "Save changes"
    And I follow "View all submissions"
    And I set the field "selectall" to "1"
    And I click on "Change marking state" "button" in the "sticky-footer" "region"
    And I click on "Change marking state" "button" in the ".modal-footer" "css_element"
    And I set the field "Marking workflow state" to "Released"
    And I set the field "Notify student" to "No"
    And I press "Save changes"
    And I run the scheduled task "mod_assign\task\cron_task"
    And I log in as "student1"
    And I open the notification popover
    Then I should not see "Assign 1" in the "#nav-notification-popover-container" "css_element"

  Scenario: Marking workflow release sends notification when control is enabled and notify checkbox is checked
    When I am on the "Assign 1" Activity page logged in as teacher1
    And I go to "Student 1" "Assign 1" activity advanced grading page
    And I set the field "Grade out of 100" to "50"
    And I set the field "Marking workflow state" to "In marking"
    And I set the field "Notify student" to "0"
    And I press "Save changes"
    And I follow "View all submissions"
    And I set the field "selectall" to "1"
    And I click on "Change marking state" "button" in the "sticky-footer" "region"
    And I click on "Change marking state" "button" in the ".modal-footer" "css_element"
    And I set the field "Marking workflow state" to "Released"
    And I set the field "Notify student" to "Yes"
    And I press "Save changes"
    And I run the scheduled task "mod_assign\task\cron_task"
    And I log in as "student1"
    And I open the notification popover
    Then I should see "Assign 1" in the "#nav-notification-popover-container" "css_element"

  Scenario: Marking workflow release sends notification when control is disabled and sendstudentnotifications is enabled
    Given the following config values are set as admin:
      | allownotifycontrol       | 0 | assign |
      | sendstudentnotifications | 1 | assign |
    When I am on the "Assign 1" Activity page logged in as teacher1
    And I go to "Student 1" "Assign 1" activity advanced grading page
    And I set the field "Grade out of 100" to "50"
    And I set the field "Marking workflow state" to "In marking"
    And I press "Save changes"
    And I follow "View all submissions"
    And I set the field "selectall" to "1"
    And I click on "Change marking state" "button" in the "sticky-footer" "region"
    And I click on "Change marking state" "button" in the ".modal-footer" "css_element"
    And I set the field "Marking workflow state" to "Released"
    And I press "Save changes"
    And I run the scheduled task "mod_assign\task\cron_task"
    And I log in as "student1"
    And I open the notification popover
    Then I should see "Assign 1" in the "#nav-notification-popover-container" "css_element"

  Scenario: Marking workflow release does not send notification when control is disabled and sendstudentnotifications is disabled
    Given the following config values are set as admin:
      | allownotifycontrol       | 0 | assign |
      | sendstudentnotifications | 0 | assign |
    When I am on the "Assign 1" Activity page logged in as teacher1
    And I go to "Student 1" "Assign 1" activity advanced grading page
    And I set the field "Grade out of 100" to "50"
    And I set the field "Marking workflow state" to "In marking"
    And I press "Save changes"
    And I follow "View all submissions"
    And I set the field "selectall" to "1"
    And I click on "Change marking state" "button" in the "sticky-footer" "region"
    And I click on "Change marking state" "button" in the ".modal-footer" "css_element"
    And I set the field "Marking workflow state" to "Released"
    And I press "Save changes"
    And I run the scheduled task "mod_assign\task\cron_task"
    And I log in as "student1"
    And I open the notification popover
    Then I should not see "Assign 1" in the "#nav-notification-popover-container" "css_element"

  Scenario: Quick grading sends notification when control is enabled and notify checkbox is checked
    Given I am on the "Assign 1" Activity page logged in as teacher1
    When I navigate to "Submissions" in current page administration
    And I click on "Quick grading" "checkbox"
    And the "checked" attribute of "[data-region='quick-grading-save'] input[type='checkbox'][name='sendstudentnotifications']" "css_element" should contain "true"
    And I set the field "User grade" to "55"
    And I click on "Save" "button" in the "sticky-footer" "region"
    And I press "Continue"
    And I set the field with xpath "//tr[contains(normalize-space(.), 'Student 1')]//select" to "Released"
    And I click on "Save" "button" in the "sticky-footer" "region"
    And I press "Continue"
    And I run the scheduled task "mod_assign\task\cron_task"
    And I log in as "student1"
    And I open the notification popover
    Then I should see "Assign 1" in the "#nav-notification-popover-container" "css_element"

  Scenario: Quick grading does not send notification when control is enabled and notify checkbox is not checked
    Given I am on the "Assign 1" Activity page logged in as teacher1
    When I navigate to "Submissions" in current page administration
    And I click on "Quick grading" "checkbox"
    And I set the field "User grade" to "56"
    And I click on "[data-region='quick-grading-save'] input[type='checkbox'][name='sendstudentnotifications']" "css_element"
    And I click on "Save" "button" in the "sticky-footer" "region"
    And I press "Continue"
    And I set the field with xpath "//tr[contains(normalize-space(.), 'Student 1')]//select" to "Released"
    And I click on "[data-region='quick-grading-save'] input[type='checkbox'][name='sendstudentnotifications']" "css_element"
    And I click on "Save" "button" in the "sticky-footer" "region"
    And I press "Continue"
    And I run the scheduled task "mod_assign\task\cron_task"
    And I log in as "student1"
    And I open the notification popover
    Then I should not see "Assign 1" in the "#nav-notification-popover-container" "css_element"

  Scenario: Quick grading sends notification when control is disabled and sendstudentnotifications is enabled
    Given the following config values are set as admin:
      | allownotifycontrol       | 0 | assign |
      | sendstudentnotifications | 1 | assign |
    When I am on the "Assign 1" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I click on "Quick grading" "checkbox"
    And I set the field "User grade" to "57"
    And I click on "Save" "button" in the "sticky-footer" "region"
    And I press "Continue"
    And I set the field with xpath "//tr[contains(normalize-space(.), 'Student 1')]//select" to "Released"
    And I click on "Save" "button" in the "sticky-footer" "region"
    And I press "Continue"
    And I run the scheduled task "mod_assign\task\cron_task"
    And I log in as "student1"
    And I open the notification popover
    Then I should see "Assign 1" in the "#nav-notification-popover-container" "css_element"

  Scenario: Quick grading does not send notification when control is disabled and sendstudentnotifications is disabled
    Given the following config values are set as admin:
      | allownotifycontrol       | 0 | assign |
      | sendstudentnotifications | 0 | assign |
    When I am on the "Assign 1" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I click on "Quick grading" "checkbox"
    And I set the field "User grade" to "58"
    And I click on "Save" "button" in the "sticky-footer" "region"
    And I press "Continue"
    And I set the field with xpath "//tr[contains(normalize-space(.), 'Student 1')]//select" to "Released"
    And I click on "Save" "button" in the "sticky-footer" "region"
    And I press "Continue"
    And I run the scheduled task "mod_assign\task\cron_task"
    And I log in as "student1"
    And I open the notification popover
    Then I should not see "Assign 1" in the "#nav-notification-popover-container" "css_element"
