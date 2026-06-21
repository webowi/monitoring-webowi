@logging
Feature:
  Ingestion endpoint authentication and validation

  Background:
    Given the following fixtures are loaded from the files:
      | log_monitoring |

  Scenario: A missing ingestion key is rejected
    Given I send a "POST" JSON request to "/api/v1/logs/ingest" with body:
      """
      {"datetime":"2026-06-21T10:00:00+00:00","level":"error","message":"no key"}
      """
    Then the response status code should be 401

  Scenario: A garbage ingestion key is rejected
    Given I set the header "X-Ingestion-Key" to "totally-bogus-key"
    And I send a "POST" JSON request to "/api/v1/logs/ingest" with body:
      """
      {"datetime":"2026-06-21T10:00:00+00:00","level":"error","message":"bad key"}
      """
    Then the response status code should be 401

  Scenario: A revoked ingestion key is rejected
    Given I set the header "X-Ingestion-Key" to "mon_ing_revoked000000000000000000000000"
    And I send a "POST" JSON request to "/api/v1/logs/ingest" with body:
      """
      {"datetime":"2026-06-21T10:00:00+00:00","level":"error","message":"revoked key"}
      """
    Then the response status code should be 401

  Scenario: A malformed payload is rejected
    Given I set the header "X-Ingestion-Key" to "mon_ing_demo0000000000000000000000000000"
    And I send a "POST" JSON request to "/api/v1/logs/ingest" with body:
      """
      {"datetime":"2026-06-21T10:00:00+00:00","level":"not-a-real-level","message":"bad level"}
      """
    Then the response status code should be 422
