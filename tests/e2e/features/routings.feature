@reset
Feature: API and routing smoke checks
  As a platform engineer
  I want lightweight endpoint checks in BDD
  So that technical routing guarantees are covered in E2E features

  Scenario: Home page uses built frontend assets
    Given I am on the "/" page
    Then the page source should contain "/build/assets/app-"
    And the page source should not contain "app.tsx"
    And the page source should not contain ":5173"

  Scenario: Booking submission API accepts valid payload and stores one event
    Given I am on the "/wizard" page
    When I submit a valid booking through the API
    Then the last API response status should be 202
    And the event store total items should be 1

  Scenario: Event store endpoint is reachable
    When I send a "GET" request to "/api/event-store"
    Then the last API response status should be 200
    And the last API response should contain "hydra:totalItems"

  Scenario: Snapshots endpoint is reachable
    When I send a "GET" request to "/api/snapshots"
    Then the last API response status should be 200
    And the last API response should contain "hydra:totalItems"
