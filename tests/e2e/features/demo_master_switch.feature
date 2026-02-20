@no-reset
Feature: Demo Master Switch
  As a demo presenter
  I want the global message bus switch to pause projections
  So that I can show inconsistency and recover with repair

  Scenario: User OFFLINE and Booking ONLINE creates inconsistency with booking flow
    Given I am on the "/demo" page
    And I set the message bus status to "ACTIVE"
    And I set the user projection status to "OFFLINE"
    And I set the booking projection status to "ONLINE"
    And I remember the current demo stats
    And I click the "Generate New Booking" button
    Then the events count should increase by 1
    And I reload the page
    And the "Repair & Sync" button should be visible
    When I click the "Repair & Sync" button
    Then the events count should increase by 1
    And I reload the page
    And the "Repair & Sync" button should not be visible
    And I set the user projection status to "ONLINE"
    And I set the booking projection status to "ONLINE"
    And I set the message bus status to "ACTIVE"

  Scenario: User ONLINE and Booking OFFLINE creates inconsistency with booking flow
    Given I am on the "/demo" page
    And I set the message bus status to "ACTIVE"
    And I set the user projection status to "ONLINE"
    And I set the booking projection status to "OFFLINE"
    And I remember the current demo stats
    When I click the "Generate New Booking" button
    Then the events count should increase by 1
    And I reload the page
    And the "Repair & Sync" button should be visible
    When I click the "Repair & Sync" button
    Then the events count should increase by 1
    And I reload the page
    And the "Repair & Sync" button should not be visible
    And I set the user projection status to "ONLINE"
    And I set the booking projection status to "ONLINE"
    And I set the message bus status to "ACTIVE"

  Scenario: User OFFLINE and Booking OFFLINE creates inconsistency with booking flow
    Given I am on the "/demo" page
    And I set the message bus status to "ACTIVE"
    And I set the user projection status to "OFFLINE"
    And I set the booking projection status to "OFFLINE"
    And I remember the current demo stats
    When I click the "Generate New Booking" button
    Then the events count should increase by 1
    And I reload the page
    And the "Repair & Sync" button should be visible
    When I click the "Repair & Sync" button
    Then the events count should increase by 1
    And I reload the page
    And the "Repair & Sync" button should not be visible
    And I set the user projection status to "ONLINE"
    And I set the booking projection status to "ONLINE"
    And I set the message bus status to "ACTIVE"

  Scenario: Register User Only works with projections online
    Given I am on the "/demo" page
    And I set the message bus status to "ACTIVE"
    And I set the user projection status to "ONLINE"
    And I set the booking projection status to "ONLINE"
    And I remember the current demo stats
    When I click the "Register User Only" button
    Then the events count should increase by 1
    And the "Repair & Sync" button should not be visible
    And I set the user projection status to "ONLINE"
    And I set the booking projection status to "ONLINE"
    And I set the message bus status to "ACTIVE"

  Scenario: Clear transactional data and rebuild from mongo restores projections
    Given I am on the "/demo" page
    And I set the message bus status to "ACTIVE"
    And I set the user projection status to "ONLINE"
    And I set the booking projection status to "ONLINE"
    And I remember the current demo stats
    When I click the "Clear Transactional Data (Postgres)" button
    Then the events count should increase by 0
    And the "users" count should be 0
    And the "bookings" count should be 0
    When I click the "Rebuild from Mongo (Events)" button
    Then the events count should increase by 0
    And the users count should match remembered baseline
    And the bookings count should match remembered baseline
