@logging
Feature:
  Log list project isolation

  Background:
    Given the following fixtures are loaded from the files:
      | log_monitoring |

  Scenario: A different organization's user cannot list another project's logs
    Given I sign in as "other@monitoring-webowi.test" with password "demo-password-please-change"
    And I send a "GET" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f/logs"
    Then the response status code should be 404
    And the JSON node "error" should be equal to "Project not found."

  Scenario: An unauthenticated request is rejected
    Given I send a "GET" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f/logs"
    Then the response status code should be 401
