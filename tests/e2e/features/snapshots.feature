@reset
Feature: Snapshot Generation
  As a platform engineer
  I want snapshots to be created automatically
  So that aggregate rebuild is optimized after threshold events

  Scenario: Booking flow creates snapshot after five events
    Given I am on the "/wizard" page
    When I submit 5 booking requests
    And I navigate to "/demo"
    Then I should see "5" in the "Historical Facts" counter
    And the snapshot count should be 1
