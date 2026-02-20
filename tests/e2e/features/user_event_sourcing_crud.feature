@reset
Feature: User CRUD with Event Sourcing
  As a platform engineer
  I want user modifications to be persisted as events first
  So that the users projection proves event sourcing behavior instead of traditional CRUD

  Scenario: Update and delete user through event sourcing pipeline
    Given I am on the "/" page
    When I create a user via API with name "User ES Original" and email "user-es-original@test.com"
    Then the user API response status should be 202
    And the users projection should include that user with name "User ES Original" and email "user-es-original@test.com"
    And the event store should contain an event of type "App\\Domain\\Event\\UserRegistered" for that user

    When I update that user via API with name "User ES Updated" and email "user-es-updated@test.com"
    Then the user API response status should be 202
    And the users projection should include that user with name "User ES Updated" and email "user-es-updated@test.com"
    And the event store should contain an event of type "App\\Domain\\Event\\UserProfileUpdated" for that user

    When I delete that user via API
    Then the user API response status should be 202
    And the users projection should not include that user
    And the event store should contain an event of type "App\\Domain\\Event\\UserDeleted" for that user

  Scenario: Create user preserves client-provided UUID
    Given I am on the "/" page
    When I create a user via API with id "01952827-62fe-7225-9f40-1577f3e90ce1" name "Client UUID User" and email "client-uuid@test.com"
    Then the user API response status should be 202
    And the users projection should include that user with name "Client UUID User" and email "client-uuid@test.com"
    And the event store should contain an event of type "App\\Domain\\Event\\UserRegistered" for that user

  Scenario: Snapshot captures latest user state after 5 accumulated changes
    Given I am on the "/" page
    When I create a user via API with name "Snapshot Seed" and email "snapshot-seed@test.com"
    Then the user API response status should be 202

    When I update that user via API with name "Snapshot v1" and email "snapshot-v1@test.com"
    Then the user API response status should be 202
    When I update that user via API with name "Snapshot v2" and email "snapshot-v2@test.com"
    Then the user API response status should be 202
    When I update that user via API with name "Snapshot v3" and email "snapshot-v3@test.com"
    Then the user API response status should be 202
    When I update that user via API with name "Snapshot v4" and email "snapshot-v4@test.com"
    Then the user API response status should be 202

    Then the snapshot store should contain a snapshot for that user with name "Snapshot v4" and email "snapshot-v4@test.com"

  Scenario: Update user rejects duplicate email with conflict
    Given I am on the "/" page
    When I create a user via API with name "First User" and email "duplicate-owner@test.com"
    Then the user API response status should be 202
    When I create a user via API with name "Second User" and email "duplicate-target@test.com"
    Then the user API response status should be 202

    When I update that user via API with name "Second User Updated" and email "duplicate-owner@test.com"
    Then the user API response status should be 409

  Scenario: Rebuild from Mongo preserves latest user state
    Given I am on the "/" page
    When I create a user via API with id "019c79fc-91df-7428-8e38-5ef418a62d93" name "bb1" and email "bb@bb.bb"
    Then the user API response status should be 202
    When I wait 1 second
    When I update that user via API with name "bb2" and email "bb@bb.bb"
    Then the user API response status should be 202
    When I wait 1 second
    When I update that user via API with name "bb3" and email "bb@bb.bb"
    Then the user API response status should be 202
    When I wait 1 second
    When I update that user via API with name "bb4" and email "bb@bb.bb"
    Then the user API response status should be 202
    When I wait 1 second
    When I update that user via API with name "bb5" and email "bb@bb.bb"
    Then the user API response status should be 202

    When I clear transactional projections
    And I rebuild projections from mongo history
    Then the users projection should include that user with name "bb5" and email "bb@bb.bb"
