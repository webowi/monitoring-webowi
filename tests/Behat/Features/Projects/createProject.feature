@projects
Feature:
  Create project

  Background:
    Given the following fixtures are loaded from the files:
      | project |

  # POST /api/v1/projects

  Scenario: Owner creates a project
    Given I sign in as "create-project-owner@monitoring-webowi.test" with password "demo1234"
    When I send a "POST" JSON request to "/api/v1/projects" with body:
      """
      {"name":"Brand New Project","platform":"symfony"}
      """
    Then the response status code should be 201
    And the JSON node "name" should be equal to "Brand New Project"
    And the JSON node "platform" should be equal to "symfony"
    And the JSON node "status" should be equal to "active"

  Scenario: Creating a project with a name that already exists is rejected
    Given I sign in as "create-project-owner@monitoring-webowi.test" with password "demo1234"
    When I send a "POST" JSON request to "/api/v1/projects" with body:
      """
      {"name":"Existing Project","platform":"symfony"}
      """
    Then the response status code should be 409
    And the JSON node "error" should be equal to "Projekt o tej nazwie już istnieje."

  Scenario: Creating a project with a blank name is rejected
    Given I sign in as "create-project-owner@monitoring-webowi.test" with password "demo1234"
    When I send a "POST" JSON request to "/api/v1/projects" with body:
      """
      {"name":"","platform":"symfony"}
      """
    Then the response status code should be 422

  Scenario: Creating a project with an invalid platform is rejected
    Given I sign in as "create-project-owner@monitoring-webowi.test" with password "demo1234"
    When I send a "POST" JSON request to "/api/v1/projects" with body:
      """
      {"name":"Another Project","platform":"not-a-real-platform"}
      """
    Then the response status code should be 422

  Scenario: Unauthenticated request to create a project is rejected
    When I send a "POST" JSON request to "/api/v1/projects" with body:
      """
      {"name":"Unauthenticated Project","platform":"symfony"}
      """
    Then the response status code should be 401
