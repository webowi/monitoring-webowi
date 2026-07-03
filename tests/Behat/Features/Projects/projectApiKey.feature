@projects
Feature:
  Project API key management

  Background:
    Given the following fixtures are loaded from the files:
      | logMonitoring |

  # GET /api/v1/projects/{uuid}
  Scenario: Owner retrieves project metadata
    Given I sign in as "owner@monitoring-webowi.test" with password "demo1234"
    When I send a "GET" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f"
    Then the response status code should be 200
    And the JSON node "name" should be equal to "Owner Monitored Project"
    And the JSON node "status" should be equal to "active"

  Scenario: Unauthenticated request for project is rejected
    When I send a "GET" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f"
    Then the response status code should be 401

  Scenario: Wrong-org user cannot access another project
    Given I sign in as "other@monitoring-webowi.test" with password "demo1234"
    When I send a "GET" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f"
    Then the response status code should be 404
    And the JSON node "error" should be equal to "Projekt nie został znaleziony."

  # GET /api/v1/projects/{uuid}/ingestion-key

  Scenario: Owner retrieves ingestion key with value and snippet
    Given I sign in as "owner@monitoring-webowi.test" with password "demo1234"
    When I send a "GET" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f/ingestion-key"
    Then the response status code should be 200
    And the JSON node "value" should be equal to "mon_ing_demo0000000000000000000000000000"
    And the JSON node "snippet" should contain "mon_ing_demo0000000000000000000000000000"

  Scenario: Unauthenticated request for ingestion key is rejected
    When I send a "GET" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f/ingestion-key"
    Then the response status code should be 401

  Scenario: Wrong-org user cannot access another project's ingestion key
    Given I sign in as "other@monitoring-webowi.test" with password "demo1234"
    When I send a "GET" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f/ingestion-key"
    Then the response status code should be 404

  # POST /api/v1/projects/{uuid}/ingestion-key/rotate

  Scenario: Owner rotates the ingestion key and receives a new value
    Given I sign in as "owner@monitoring-webowi.test" with password "demo1234"
    When I send a "POST" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f/ingestion-key/rotate"
    Then the response status code should be 200
    And the JSON node "value" should contain "mon_ing_"
    And the JSON node "snippet" should contain "NOT NULL"

  Scenario: After rotation the old key is rejected on ingest
    Given I sign in as "owner@monitoring-webowi.test" with password "demo1234"
    When I send a "POST" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f/ingestion-key/rotate"
    Then the response status code should be 200
    When I set the header "X-Ingestion-Key" to "mon_ing_demo0000000000000000000000000000"
    And I send a "POST" JSON request to "/api/v1/logs/ingest" with body:
      """
      {"datetime":"2026-06-27T10:00:00+00:00","level":"error","message":"old key should be rejected"}
      """
    Then the response status code should be 401

  Scenario: Unauthenticated request to rotate is rejected
    When I send a "POST" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f/ingestion-key/rotate"
    Then the response status code should be 401

  Scenario: Wrong-org user cannot rotate another project's key
    Given I sign in as "other@monitoring-webowi.test" with password "demo1234"
    When I send a "POST" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f/ingestion-key/rotate"
    Then the response status code should be 404
    And the JSON node "error" should be equal to "Projekt nie został znaleziony."
