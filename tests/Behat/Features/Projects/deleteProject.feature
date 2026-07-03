@projects
Feature:
  Delete project

  Background:
    Given the following fixtures are loaded from the files:
      | logMonitoring |

  # DELETE /api/v1/projects/{uuid}

  Scenario: Owner deletes their own project
    Given I sign in as "owner@monitoring-webowi.test" with password "demo1234"
    When I send a "DELETE" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f"
    Then the response status code should be 204
    When I send a "GET" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f"
    Then the response status code should be 404

  Scenario: Wrong-org user cannot delete another project
    Given I sign in as "other@monitoring-webowi.test" with password "demo1234"
    When I send a "DELETE" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f"
    Then the response status code should be 404
    And the JSON node "error" should be equal to "Projekt nie został znaleziony."

  Scenario: Deleting a non-existent project is rejected
    Given I sign in as "owner@monitoring-webowi.test" with password "demo1234"
    When I send a "DELETE" JSON request to "/api/v1/projects/00000000-0000-4000-8000-000000000000"
    Then the response status code should be 404

  Scenario: Unauthenticated request to delete a project is rejected
    When I send a "DELETE" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f"
    Then the response status code should be 401
