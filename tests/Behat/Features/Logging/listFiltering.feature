@logging
Feature:
  Log list filtering by severity and HTTP status code

  Background:
    Given the following fixtures are loaded from the files:
      | logMonitoring |
    And I set the header "X-Ingestion-Key" to "mon_ing_demo0000000000000000000000000000"
    And I send a "POST" JSON request to "/api/v1/logs/ingest" with body:
      """
      {"datetime":"2026-06-21T10:00:00+00:00","level":"error","message":"Error with 500","context":{"http_status_code":500}}
      """
    Then the response status code should be 202
    Then I send a "POST" JSON request to "/api/v1/logs/ingest" with body:
      """
      {"datetime":"2026-06-21T10:01:00+00:00","level":"critical","message":"Critical with 503","context":{"http_status_code":503}}
      """
    Then the response status code should be 202
    Then I send a "POST" JSON request to "/api/v1/logs/ingest" with body:
      """
      {"datetime":"2026-06-21T10:02:00+00:00","level":"warning","message":"Warning with 404","context":{"http_status_code":404}}
      """
    Then the response status code should be 202
    Then I send a "POST" JSON request to "/api/v1/logs/ingest" with body:
      """
      {"datetime":"2026-06-21T10:03:00+00:00","level":"info","message":"Info with no http code"}
      """
    Then the response status code should be 202
    Then I send a "POST" JSON request to "/api/v1/logs/ingest" with body:
      """
      {"datetime":"2026-06-21T10:04:00+00:00","level":"error","message":"Error with 200 control","context":{"http_status_code":200}}
      """
    Then the response status code should be 202

  Scenario: Filtering by a single severity returns only matching entries
    Given I sign in as "owner@monitoring-webowi.test" with password "demo1234"
    When I send a "GET" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f/logs?severity=error"
    Then the response status code should be 200
    And the JSON node "" should be an array with 2 elements
    And the JSON node "[0].message" should be equal to "Error with 200 control"
    And the JSON node "[1].message" should be equal to "Error with 500"

  Scenario: Filtering by multiple comma-separated severities returns the union
    Given I sign in as "owner@monitoring-webowi.test" with password "demo1234"
    When I send a "GET" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f/logs?severity=error,critical"
    Then the response status code should be 200
    And the JSON node "" should be an array with 3 elements
    And the JSON node "[0].message" should be equal to "Error with 200 control"
    And the JSON node "[1].message" should be equal to "Critical with 503"
    And the JSON node "[2].message" should be equal to "Error with 500"

  Scenario: Filtering by an exact HTTP status code returns only that code
    Given I sign in as "owner@monitoring-webowi.test" with password "demo1234"
    When I send a "GET" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f/logs?httpStatusCode=500"
    Then the response status code should be 200
    And the JSON node "" should be an array with 1 elements
    And the JSON node "[0].message" should be equal to "Error with 500"

  Scenario: Filtering by an HTTP status code class shorthand returns the whole range
    Given I sign in as "owner@monitoring-webowi.test" with password "demo1234"
    When I send a "GET" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f/logs?httpStatusCode=5xx"
    Then the response status code should be 200
    And the JSON node "" should be an array with 2 elements
    And the JSON node "[0].message" should be equal to "Critical with 503"
    And the JSON node "[1].message" should be equal to "Error with 500"

  Scenario: Combining severity and HTTP status code filters applies AND semantics
    Given I sign in as "owner@monitoring-webowi.test" with password "demo1234"
    When I send a "GET" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f/logs?severity=error&httpStatusCode=500"
    Then the response status code should be 200
    And the JSON node "" should be an array with 1 elements
    And the JSON node "[0].message" should be equal to "Error with 500"

  Scenario: An unknown severity value is rejected
    Given I sign in as "owner@monitoring-webowi.test" with password "demo1234"
    When I send a "GET" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f/logs?severity=bogus"
    Then the response status code should be 422

  Scenario: A malformed HTTP status code value is rejected
    Given I sign in as "owner@monitoring-webowi.test" with password "demo1234"
    When I send a "GET" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f/logs?httpStatusCode=999"
    Then the response status code should be 422
