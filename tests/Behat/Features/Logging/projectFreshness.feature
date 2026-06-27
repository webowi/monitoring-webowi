@logging
Feature:
  Project freshness indicator

  Background:
    Given the following fixtures are loaded from the files:
      | logMonitoring |

  Scenario: Freshness returns null when no logs have been ingested
    Given I sign in as "owner@monitoring-webowi.test" with password "demo1234"
    When I send a "GET" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f/freshness"
    Then the response status code should be 200
    And the JSON node "lastLogReceivedAt" should contain "NULL"

  Scenario: Freshness returns a timestamp after a log is ingested
    Given I set the header "X-Ingestion-Key" to "mon_ing_demo0000000000000000000000000000"
    And I send a "POST" JSON request to "/api/v1/logs/ingest" with body:
      """
      {"datetime":"2026-06-27T10:00:00+00:00","level":"error","message":"Freshness test log"}
      """
    Then the response status code should be 202
    Given I sign in as "owner@monitoring-webowi.test" with password "demo1234"
    When I send a "GET" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f/freshness"
    Then the response status code should be 200
    And the JSON node "lastLogReceivedAt" should contain "NOT NULL"

  Scenario: A different organisation's user cannot access another project's freshness
    Given I sign in as "other@monitoring-webowi.test" with password "demo1234"
    When I send a "GET" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f/freshness"
    Then the response status code should be 404
    And the JSON node "error" should be equal to "Project not found."

  Scenario: An unauthenticated request is rejected
    When I send a "GET" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f/freshness"
    Then the response status code should be 401
