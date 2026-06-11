@user
Feature:
  User

  Background:
    Given the following fixtures are loaded from the files:
      | user |

  Scenario: As a anonymous user without admin permissions I cannot see admin dashboard
    Given I am authorized as "---"
    And I send a GET JSON request to "/dashboard"
    Then the response status code should be 302
    Then the response should redirect to "/login"

  Scenario: As a anonymous user with admin permissions I cannot see admin dashboard
    Given I am authorized as "ROLE_USER"
    And I send a GET JSON request to "/dashboard"
    Then the response status code should be 302
    Then the response should redirect to "/login"

  Scenario: As a anonymous user with admin permissions I cannot see admin dashboard
    Given I am authorized as "ROLE_ADMIN"
    And I send a GET JSON request to "/dashboard"
    Then the response status code should be 302
