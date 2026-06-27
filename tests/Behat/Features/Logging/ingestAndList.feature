@logging
Feature:
  Log ingestion and listing

  Background:
    Given the following fixtures are loaded from the files:
      | logMonitoring |

  Scenario: A valid key ingests a log entry and the owner sees it normalized in the list
    Given I set the header "X-Ingestion-Key" to "mon_ing_demo0000000000000000000000000000"
    And I send a "POST" JSON request to "/api/v1/logs/ingest" with body:
      """
      {"datetime":"2026-06-21T10:00:00+00:00","level":"error","message":"Ingest and list happy path","context":{"http_status_code":500,"exception":{"class":"App\\Some\\Exception"}}}
      """
    Then the response status code should be 202
    And the JSON node "status" should be equal to "accepted"
    Then I am authorized as "owner@monitoring-webowi.test" with password "demo1234"
    And I send a "GET" JSON request to "/api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f/logs"
    Then the response status code should be 200
    And the JSON node "[0].message" should be equal to "Ingest and list happy path"
    And the JSON node "[0].httpStatusCode" should be equal to "500"
    And the JSON node "[0].exceptionClass" should be equal to "App\Some\Exception"
