@reset
Feature: Functional parity from Symfony functional tests
  As a platform engineer
  I want key functional guarantees represented in Gherkin
  So that E2E coverage reflects backend functional expectations

  Scenario: Home page uses built frontend assets
    Given I am on the "/" page
    Then the page source should contain "/build/assets/app-"
    And the page source should not contain "app.tsx"
    And the page source should not contain ":5173"

  Scenario: Valid booking submission is accepted and stored
    Given I am on the "/wizard" page
    When I submit a valid booking through the API
    Then the last API response status should be 202
    And the event store total items should be 1
