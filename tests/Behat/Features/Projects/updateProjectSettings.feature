@projects
Feature:
  Update project settings

  Background:
    Given the following fixtures are loaded from the files:
      | logMonitoring |

  # PATCH /api/v1/projects/{uuid}

  Scenario: Owner updates name, platform and status in one save
    Given I sign in as "owner@monitoring-webowi.test" with password "demo1234"
    When I send a "PATCH" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f" with body:
      """
      {"name":"Renamed Project","platform":"vite_react","status":"inactive"}
      """
    Then the response status code should be 200
    And the JSON node "name" should be equal to "Renamed Project"
    And the JSON node "platform" should be equal to "vite_react"
    And the JSON node "status" should be equal to "inactive"
    When I send a "GET" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f"
    Then the JSON node "name" should be equal to "Renamed Project"
    And the JSON node "platform" should be equal to "vite_react"
    And the JSON node "status" should be equal to "inactive"

  Scenario: Owner updates only the platform, leaving the name unchanged
    Given I sign in as "owner@monitoring-webowi.test" with password "demo1234"
    When I send a "PATCH" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f" with body:
      """
      {"name":"Owner Monitored Project","platform":"vite_react"}
      """
    Then the response status code should be 200
    And the JSON node "name" should be equal to "Owner Monitored Project"
    And the JSON node "platform" should be equal to "vite_react"

  Scenario: Updating with a blank name is rejected
    Given I sign in as "owner@monitoring-webowi.test" with password "demo1234"
    When I send a "PATCH" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f" with body:
      """
      {"name":""}
      """
    Then the response status code should be 422

  Scenario: Updating with an invalid platform is rejected
    Given I sign in as "owner@monitoring-webowi.test" with password "demo1234"
    When I send a "PATCH" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f" with body:
      """
      {"platform":"not-a-real-platform"}
      """
    Then the response status code should be 422

  Scenario: Renaming to a name that already exists is rejected
    Given I sign in as "owner@monitoring-webowi.test" with password "demo1234"
    When I send a "PATCH" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f" with body:
      """
      {"name":"Another Owner Project"}
      """
    Then the response status code should be 409
    And the JSON node "error" should be equal to "Projekt o tej nazwie już istnieje."

  Scenario: Wrong-org user cannot update another project
    Given I sign in as "other@monitoring-webowi.test" with password "demo1234"
    When I send a "PATCH" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f" with body:
      """
      {"name":"Hijacked Name"}
      """
    Then the response status code should be 404
    And the JSON node "error" should be equal to "Projekt nie został znaleziony."

  Scenario: Unauthenticated request to update a project is rejected
    When I send a "PATCH" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f" with body:
      """
      {"name":"Hijacked Name"}
      """
    Then the response status code should be 401
