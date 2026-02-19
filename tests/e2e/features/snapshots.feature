@reset
Feature: Snapshot Generation
  As a platform engineer
  I want snapshots to be created automatically
  So that aggregate rebuild is optimized after threshold events

  Scenario: Booking flow with different aggregate IDs does not create snapshots
    Given I am on the "/wizard" page
    When I submit 5 booking requests
    And I navigate to "/demo"
    Then I should see "5" in the "Historical Facts" counter
    And the event store total items should be 5
    And the snapshot count should be 0

  Scenario: User aggregate creates snapshot after five events on same aggregate
    Given I am on the "/" page
    When I create a user aggregate with 5 events
    Then the user aggregate snapshot count should be 1
