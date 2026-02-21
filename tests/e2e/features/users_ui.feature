@reset
Feature: Users UI Management
  As a product user
  I want to create, edit and delete users from the menu
  So that each action goes through event sourcing and updates projections

  Scenario: Create, edit and delete a user from Users menu
    Given I am on the "/" page
    When I navigate to "/users"
    Then I should see "Users Management"
    When I click the "Create User" button
    Then the URL should contain "/users/new"
    When I fill in "Name" with "UI Seed"
    And I fill in "Email" with "ui-seed@test.com"
    And I click the "Create User" button
    Then I should see "User created through event stream."
    And the table should contain "ui-seed@test.com"
    And I remember user id by email "ui-seed@test.com" from users projection
    And the event store should contain an event of type "App\\Domain\\Event\\UserRegistered" for that user

    When I click the "Edit User" button
    Then the URL should contain "/users/"
    And the URL should contain "/edit"
    And I fill in "Name" with "UI Updated"
    And I fill in "Email" with "ui-updated@test.com"
    And I click the "Save User Changes" button
    Then the table should contain "ui-updated@test.com"
    And the event store should contain an event of type "App\\Domain\\Event\\UserProfileUpdated" for that user

    When I click the "Delete User" button
    Then I should see "No users in projection."
    And the event store should contain an event of type "App\\Domain\\Event\\UserDeleted" for that user

  Scenario: Edit user generated from demo booking keeps latest name in users list
    Given I am on the "/demo" page
    And I remember the current demo stats
    When I click the "Generate New Booking" button
    Then the users count should increase by 1

    When I navigate to "/users"
    Then I should see "Users Management"
    When I click the "Edit User" button
    And I fill in "Name" with "Edited From Demo Flow"
    And I click the "Save User Changes" button
    Then the table should contain "Edited From Demo Flow"
