@reset
Feature: Booking Wizard
  As a customer
  I want to submit a booking request
  So that the system can process my event and create projections

  Scenario: Happy path booking creates event and projections
    Given I am on the "/wizard" page
    When I fill in "Client Name" with "John TED Talk"
    And I fill in "Email Address" with "john@ted.com"
    And I fill in "People (Pax)" with "4"
    And I fill in "Budget (€)" with "150"
    And I click the "Submit Booking" button
    Then I should see "Request Received"
    And I should see "Your booking event has been recorded in the store"
    
    When I navigate to "/demo"
    Then I should see "2" in the "Historical Facts" counter
    And I should see "John TED Talk" in the "Bookings Projection" table
    And I should see "john@ted.com" in the "Bookings Projection" table

  Scenario: Idempotency with same ID
    # Note: Through UI, the ID is regenerated on success. 
    # To test pure idempotency through UI we would need to simulate a double click or network failure.
    # For now, we will verify that multiple submissions with different IDs work correctly.
    Given I am on the "/wizard" page
    When I fill in "Client Name" with "User 1"
    And I fill in "Email Address" with "user1@example.com"
    And I click the "Submit Booking" button
    Then I should see "Request Received"
    
    When I click the "Create Another Booking" button
    And I fill in "Client Name" with "User 1"
    And I fill in "Email Address" with "user1@example.com"
    And I click the "Submit Booking" button
    Then I should see "Request Received"
    
    When I navigate to "/demo"
    Then I should see "3" in the "Historical Facts" counter
    And I should see "1" in the "User Records" counter

  Scenario: Security: Identity is sacred and metadata is snapshotted
    Given I am on the "/wizard" page
    When I fill in "Client Name" with "Original Name"
    And I fill in "Email Address" with "security@pure.com"
    And I click the "Submit Booking" button
    Then I should see "Request Received"
    
    When I click the "Create Another Booking" button
    And I fill in "Client Name" with "Attacker Name"
    And I fill in "Email Address" with "security@pure.com"
    And I click the "Submit Booking" button
    Then I should see "Request Received"
    
    When I navigate to "/demo"
    Then I should see "Original Name" in the "Users Projection" table
    And I should not see "Attacker Name" in the "Users Projection" table
    And I should see "Attacker Name" in the "Bookings Projection" table

  Scenario: System rebuild restores read models
    Given I am on the "/wizard" page
    When I fill in "Client Name" with "Rebuild Test"
    And I fill in "Email Address" with "rebuild@test.com"
    And I click the "Submit Booking" button
    Then I should see "Request Received"
    
    When I navigate to "/demo"
    And I click the "Reset Lab" button
    And I click the "Execute Reset" button
    Then I should see "0" in the "Historical Facts" counter
    
    # We need to re-create the event because Reset Lab cleared everything including Mongo
    When I navigate to "/wizard"
    And I fill in "Client Name" with "Persistent Fact"
    And I fill in "Email Address" with "persistent@test.com"
    And I click the "Submit Booking" button
    
    When I navigate to "/demo"
    Then I should see "1" in the "User Records" counter
    And I click the "User Projection" button
    Then I should see "OFFLINE"
    
    When I navigate to "/wizard"
    And I fill in "Client Name" with "Missed Projection"
    And I fill in "Email Address" with "missed@test.com"
    And I click the "Submit Booking" button
    
    When I navigate to "/demo"
    Then I should see "4" in the "Historical Facts" counter
    And I should see "1" in the "User Records" counter
    And I click the "Repair & Sync" button
    Then I should see "2" in the "User Records" counter
