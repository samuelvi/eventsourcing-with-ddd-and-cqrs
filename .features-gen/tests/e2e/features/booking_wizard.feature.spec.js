// Generated from: tests/e2e/features/booking_wizard.feature
import { test } from '../../../../tests/e2e/common/bdd.ts';

test.describe('Booking Wizard', () => {
    test(
        'Happy path booking creates event and projections',
        { tag: ['@reset'] },
        async ({ Given, When, Then, And, page }) => {
            await Given('I am on the "/wizard" page', null, { page });
            await When('I fill in "Client Name" with "John TED Talk"', null, { page });
            await And('I fill in "Email Address" with "john@ted.com"', null, { page });
            await And('I fill in "People (Pax)" with "4"', null, { page });
            await And('I fill in "Budget (€)" with "150"', null, { page });
            await And('I click the "Submit Booking" button', null, { page });
            await Then('I should see "Request Received"', null, { page });
            await And('I should see "Your booking event has been recorded in the store"', null, {
                page
            });
            await When('I navigate to "/demo"', null, { page });
            await Then('I should see "1" in the "Historical Facts" counter', null, { page });
            await And('I should see "John TED Talk" in the "Bookings Projection" table', null, {
                page
            });
            await And('I should see "john@ted.com" in the "Bookings Projection" table', null, {
                page
            });
        }
    );

    test(
        'Idempotency with same ID',
        { tag: ['@reset'] },
        async ({ Given, When, Then, And, page }) => {
            await Given('I am on the "/wizard" page', null, { page });
            await When('I fill in "Client Name" with "User 1"', null, { page });
            await And('I fill in "Email Address" with "user1@example.com"', null, { page });
            await And('I click the "Submit Booking" button', null, { page });
            await Then('I should see "Request Received"', null, { page });
            await When('I click the "Create Another Booking" button', null, { page });
            await And('I fill in "Client Name" with "User 1"', null, { page });
            await And('I fill in "Email Address" with "user1@example.com"', null, { page });
            await And('I click the "Submit Booking" button', null, { page });
            await Then('I should see "Request Received"', null, { page });
            await When('I navigate to "/demo"', null, { page });
            await Then('I should see "2" in the "Historical Facts" counter', null, { page });
            await And('I should see "1" in the "User Records" counter', null, { page });
        }
    );

    test(
        'Security: Identity is sacred and metadata is snapshotted',
        { tag: ['@reset'] },
        async ({ Given, When, Then, And, page }) => {
            await Given('I am on the "/wizard" page', null, { page });
            await When('I fill in "Client Name" with "Original Name"', null, { page });
            await And('I fill in "Email Address" with "security@pure.com"', null, { page });
            await And('I click the "Submit Booking" button', null, { page });
            await Then('I should see "Request Received"', null, { page });
            await When('I click the "Create Another Booking" button', null, { page });
            await And('I fill in "Client Name" with "Attacker Name"', null, { page });
            await And('I fill in "Email Address" with "security@pure.com"', null, { page });
            await And('I click the "Submit Booking" button', null, { page });
            await Then('I should see "Request Received"', null, { page });
            await When('I navigate to "/demo"', null, { page });
            await Then('I should see "Original Name" in the "Users Projection" table', null, {
                page
            });
            await And('I should not see "Attacker Name" in the "Users Projection" table', null, {
                page
            });
            await And('I should see "Attacker Name" in the "Bookings Projection" table', null, {
                page
            });
        }
    );

    test(
        'System rebuild restores read models',
        { tag: ['@reset'] },
        async ({ Given, When, Then, And, page }) => {
            await Given('I am on the "/wizard" page', null, { page });
            await When('I fill in "Client Name" with "Rebuild Test"', null, { page });
            await And('I fill in "Email Address" with "rebuild@test.com"', null, { page });
            await And('I click the "Submit Booking" button', null, { page });
            await Then('I should see "Request Received"', null, { page });
            await When('I navigate to "/demo"', null, { page });
            await And('I click the "Reset Lab" button', null, { page });
            await And('I click the "Execute Reset" button', null, { page });
            await Then('I should see "0" in the "Historical Facts" counter', null, { page });
            await When('I navigate to "/wizard"', null, { page });
            await And('I fill in "Client Name" with "Persistent Fact"', null, { page });
            await And('I fill in "Email Address" with "persistent@test.com"', null, { page });
            await And('I click the "Submit Booking" button', null, { page });
            await When('I navigate to "/demo"', null, { page });
            await And('I click the "User Projection" button', null, { page });
            await Then('I should see "OFFLINE"', null, { page });
            await When('I navigate to "/wizard"', null, { page });
            await And('I fill in "Client Name" with "Missed Projection"', null, { page });
            await And('I fill in "Email Address" with "missed@test.com"', null, { page });
            await And('I click the "Submit Booking" button', null, { page });
            await When('I navigate to "/demo"', null, { page });
            await Then('I should see "2" in the "Historical Facts" counter', null, { page });
            await And('I should see "1" in the "User Records" counter', null, { page });
            await And('I click the "Repair & Sync" button', null, { page });
            await Then('I should see "2" in the "User Records" counter', null, { page });
        }
    );
});

// == technical section ==

test.use({
    $test: [({}, use) => use(test), { scope: 'test', box: true }],
    $uri: [
        ({}, use) => use('tests/e2e/features/booking_wizard.feature'),
        { scope: 'test', box: true }
    ],
    $bddFileData: [({}, use) => use(bddFileData), { scope: 'test', box: true }]
});

const bddFileData = [
    // bdd-data-start
    {
        pwTestLine: 6,
        pickleLine: 7,
        tags: ['@reset'],
        steps: [
            {
                pwStepLine: 7,
                gherkinStepLine: 8,
                keywordType: 'Context',
                textWithKeyword: 'Given I am on the "/wizard" page',
                stepMatchArguments: [
                    {
                        group: {
                            start: 12,
                            value: '"/wizard"',
                            children: [
                                { start: 13, value: '/wizard', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 8,
                gherkinStepLine: 9,
                keywordType: 'Action',
                textWithKeyword: 'When I fill in "Client Name" with "John TED Talk"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 10,
                            value: '"Client Name"',
                            children: [
                                { start: 11, value: 'Client Name', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 29,
                            value: '"John TED Talk"',
                            children: [
                                { start: 30, value: 'John TED Talk', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 9,
                gherkinStepLine: 10,
                keywordType: 'Action',
                textWithKeyword: 'And I fill in "Email Address" with "john@ted.com"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 10,
                            value: '"Email Address"',
                            children: [
                                { start: 11, value: 'Email Address', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 31,
                            value: '"john@ted.com"',
                            children: [
                                { start: 32, value: 'john@ted.com', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 10,
                gherkinStepLine: 11,
                keywordType: 'Action',
                textWithKeyword: 'And I fill in "People (Pax)" with "4"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 10,
                            value: '"People (Pax)"',
                            children: [
                                { start: 11, value: 'People (Pax)', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 30,
                            value: '"4"',
                            children: [
                                { start: 31, value: '4', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 11,
                gherkinStepLine: 12,
                keywordType: 'Action',
                textWithKeyword: 'And I fill in "Budget (€)" with "150"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 10,
                            value: '"Budget (€)"',
                            children: [
                                { start: 11, value: 'Budget (€)', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 28,
                            value: '"150"',
                            children: [
                                { start: 29, value: '150', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 12,
                gherkinStepLine: 13,
                keywordType: 'Action',
                textWithKeyword: 'And I click the "Submit Booking" button',
                stepMatchArguments: [
                    {
                        group: {
                            start: 12,
                            value: '"Submit Booking"',
                            children: [
                                {
                                    start: 13,
                                    value: 'Submit Booking',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 13,
                gherkinStepLine: 14,
                keywordType: 'Outcome',
                textWithKeyword: 'Then I should see "Request Received"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 13,
                            value: '"Request Received"',
                            children: [
                                {
                                    start: 14,
                                    value: 'Request Received',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 14,
                gherkinStepLine: 15,
                keywordType: 'Outcome',
                textWithKeyword:
                    'And I should see "Your booking event has been recorded in the store"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 13,
                            value: '"Your booking event has been recorded in the store"',
                            children: [
                                {
                                    start: 14,
                                    value: 'Your booking event has been recorded in the store',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 15,
                gherkinStepLine: 17,
                keywordType: 'Action',
                textWithKeyword: 'When I navigate to "/demo"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 14,
                            value: '"/demo"',
                            children: [
                                { start: 15, value: '/demo', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 16,
                gherkinStepLine: 18,
                keywordType: 'Outcome',
                textWithKeyword: 'Then I should see "1" in the "Historical Facts" counter',
                stepMatchArguments: [
                    {
                        group: {
                            start: 13,
                            value: '"1"',
                            children: [
                                { start: 14, value: '1', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 24,
                            value: '"Historical Facts"',
                            children: [
                                {
                                    start: 25,
                                    value: 'Historical Facts',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 17,
                gherkinStepLine: 19,
                keywordType: 'Outcome',
                textWithKeyword:
                    'And I should see "John TED Talk" in the "Bookings Projection" table',
                stepMatchArguments: [
                    {
                        group: {
                            start: 13,
                            value: '"John TED Talk"',
                            children: [
                                { start: 14, value: 'John TED Talk', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 36,
                            value: '"Bookings Projection"',
                            children: [
                                {
                                    start: 37,
                                    value: 'Bookings Projection',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 18,
                gherkinStepLine: 20,
                keywordType: 'Outcome',
                textWithKeyword:
                    'And I should see "john@ted.com" in the "Bookings Projection" table',
                stepMatchArguments: [
                    {
                        group: {
                            start: 13,
                            value: '"john@ted.com"',
                            children: [
                                { start: 14, value: 'john@ted.com', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 35,
                            value: '"Bookings Projection"',
                            children: [
                                {
                                    start: 36,
                                    value: 'Bookings Projection',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            }
        ]
    },
    {
        pwTestLine: 21,
        pickleLine: 22,
        tags: ['@reset'],
        steps: [
            {
                pwStepLine: 22,
                gherkinStepLine: 26,
                keywordType: 'Context',
                textWithKeyword: 'Given I am on the "/wizard" page',
                stepMatchArguments: [
                    {
                        group: {
                            start: 12,
                            value: '"/wizard"',
                            children: [
                                { start: 13, value: '/wizard', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 23,
                gherkinStepLine: 27,
                keywordType: 'Action',
                textWithKeyword: 'When I fill in "Client Name" with "User 1"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 10,
                            value: '"Client Name"',
                            children: [
                                { start: 11, value: 'Client Name', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 29,
                            value: '"User 1"',
                            children: [
                                { start: 30, value: 'User 1', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 24,
                gherkinStepLine: 28,
                keywordType: 'Action',
                textWithKeyword: 'And I fill in "Email Address" with "user1@example.com"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 10,
                            value: '"Email Address"',
                            children: [
                                { start: 11, value: 'Email Address', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 31,
                            value: '"user1@example.com"',
                            children: [
                                {
                                    start: 32,
                                    value: 'user1@example.com',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 25,
                gherkinStepLine: 29,
                keywordType: 'Action',
                textWithKeyword: 'And I click the "Submit Booking" button',
                stepMatchArguments: [
                    {
                        group: {
                            start: 12,
                            value: '"Submit Booking"',
                            children: [
                                {
                                    start: 13,
                                    value: 'Submit Booking',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 26,
                gherkinStepLine: 30,
                keywordType: 'Outcome',
                textWithKeyword: 'Then I should see "Request Received"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 13,
                            value: '"Request Received"',
                            children: [
                                {
                                    start: 14,
                                    value: 'Request Received',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 27,
                gherkinStepLine: 32,
                keywordType: 'Action',
                textWithKeyword: 'When I click the "Create Another Booking" button',
                stepMatchArguments: [
                    {
                        group: {
                            start: 12,
                            value: '"Create Another Booking"',
                            children: [
                                {
                                    start: 13,
                                    value: 'Create Another Booking',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 28,
                gherkinStepLine: 33,
                keywordType: 'Action',
                textWithKeyword: 'And I fill in "Client Name" with "User 1"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 10,
                            value: '"Client Name"',
                            children: [
                                { start: 11, value: 'Client Name', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 29,
                            value: '"User 1"',
                            children: [
                                { start: 30, value: 'User 1', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 29,
                gherkinStepLine: 34,
                keywordType: 'Action',
                textWithKeyword: 'And I fill in "Email Address" with "user1@example.com"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 10,
                            value: '"Email Address"',
                            children: [
                                { start: 11, value: 'Email Address', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 31,
                            value: '"user1@example.com"',
                            children: [
                                {
                                    start: 32,
                                    value: 'user1@example.com',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 30,
                gherkinStepLine: 35,
                keywordType: 'Action',
                textWithKeyword: 'And I click the "Submit Booking" button',
                stepMatchArguments: [
                    {
                        group: {
                            start: 12,
                            value: '"Submit Booking"',
                            children: [
                                {
                                    start: 13,
                                    value: 'Submit Booking',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 31,
                gherkinStepLine: 36,
                keywordType: 'Outcome',
                textWithKeyword: 'Then I should see "Request Received"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 13,
                            value: '"Request Received"',
                            children: [
                                {
                                    start: 14,
                                    value: 'Request Received',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 32,
                gherkinStepLine: 38,
                keywordType: 'Action',
                textWithKeyword: 'When I navigate to "/demo"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 14,
                            value: '"/demo"',
                            children: [
                                { start: 15, value: '/demo', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 33,
                gherkinStepLine: 39,
                keywordType: 'Outcome',
                textWithKeyword: 'Then I should see "2" in the "Historical Facts" counter',
                stepMatchArguments: [
                    {
                        group: {
                            start: 13,
                            value: '"2"',
                            children: [
                                { start: 14, value: '2', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 24,
                            value: '"Historical Facts"',
                            children: [
                                {
                                    start: 25,
                                    value: 'Historical Facts',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 34,
                gherkinStepLine: 40,
                keywordType: 'Outcome',
                textWithKeyword: 'And I should see "1" in the "User Records" counter',
                stepMatchArguments: [
                    {
                        group: {
                            start: 13,
                            value: '"1"',
                            children: [
                                { start: 14, value: '1', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 24,
                            value: '"User Records"',
                            children: [
                                { start: 25, value: 'User Records', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            }
        ]
    },
    {
        pwTestLine: 37,
        pickleLine: 42,
        tags: ['@reset'],
        steps: [
            {
                pwStepLine: 38,
                gherkinStepLine: 43,
                keywordType: 'Context',
                textWithKeyword: 'Given I am on the "/wizard" page',
                stepMatchArguments: [
                    {
                        group: {
                            start: 12,
                            value: '"/wizard"',
                            children: [
                                { start: 13, value: '/wizard', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 39,
                gherkinStepLine: 44,
                keywordType: 'Action',
                textWithKeyword: 'When I fill in "Client Name" with "Original Name"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 10,
                            value: '"Client Name"',
                            children: [
                                { start: 11, value: 'Client Name', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 29,
                            value: '"Original Name"',
                            children: [
                                { start: 30, value: 'Original Name', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 40,
                gherkinStepLine: 45,
                keywordType: 'Action',
                textWithKeyword: 'And I fill in "Email Address" with "security@pure.com"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 10,
                            value: '"Email Address"',
                            children: [
                                { start: 11, value: 'Email Address', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 31,
                            value: '"security@pure.com"',
                            children: [
                                {
                                    start: 32,
                                    value: 'security@pure.com',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 41,
                gherkinStepLine: 46,
                keywordType: 'Action',
                textWithKeyword: 'And I click the "Submit Booking" button',
                stepMatchArguments: [
                    {
                        group: {
                            start: 12,
                            value: '"Submit Booking"',
                            children: [
                                {
                                    start: 13,
                                    value: 'Submit Booking',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 42,
                gherkinStepLine: 47,
                keywordType: 'Outcome',
                textWithKeyword: 'Then I should see "Request Received"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 13,
                            value: '"Request Received"',
                            children: [
                                {
                                    start: 14,
                                    value: 'Request Received',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 43,
                gherkinStepLine: 49,
                keywordType: 'Action',
                textWithKeyword: 'When I click the "Create Another Booking" button',
                stepMatchArguments: [
                    {
                        group: {
                            start: 12,
                            value: '"Create Another Booking"',
                            children: [
                                {
                                    start: 13,
                                    value: 'Create Another Booking',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 44,
                gherkinStepLine: 50,
                keywordType: 'Action',
                textWithKeyword: 'And I fill in "Client Name" with "Attacker Name"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 10,
                            value: '"Client Name"',
                            children: [
                                { start: 11, value: 'Client Name', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 29,
                            value: '"Attacker Name"',
                            children: [
                                { start: 30, value: 'Attacker Name', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 45,
                gherkinStepLine: 51,
                keywordType: 'Action',
                textWithKeyword: 'And I fill in "Email Address" with "security@pure.com"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 10,
                            value: '"Email Address"',
                            children: [
                                { start: 11, value: 'Email Address', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 31,
                            value: '"security@pure.com"',
                            children: [
                                {
                                    start: 32,
                                    value: 'security@pure.com',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 46,
                gherkinStepLine: 52,
                keywordType: 'Action',
                textWithKeyword: 'And I click the "Submit Booking" button',
                stepMatchArguments: [
                    {
                        group: {
                            start: 12,
                            value: '"Submit Booking"',
                            children: [
                                {
                                    start: 13,
                                    value: 'Submit Booking',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 47,
                gherkinStepLine: 53,
                keywordType: 'Outcome',
                textWithKeyword: 'Then I should see "Request Received"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 13,
                            value: '"Request Received"',
                            children: [
                                {
                                    start: 14,
                                    value: 'Request Received',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 48,
                gherkinStepLine: 55,
                keywordType: 'Action',
                textWithKeyword: 'When I navigate to "/demo"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 14,
                            value: '"/demo"',
                            children: [
                                { start: 15, value: '/demo', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 49,
                gherkinStepLine: 56,
                keywordType: 'Outcome',
                textWithKeyword:
                    'Then I should see "Original Name" in the "Users Projection" table',
                stepMatchArguments: [
                    {
                        group: {
                            start: 13,
                            value: '"Original Name"',
                            children: [
                                { start: 14, value: 'Original Name', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 36,
                            value: '"Users Projection"',
                            children: [
                                {
                                    start: 37,
                                    value: 'Users Projection',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 50,
                gherkinStepLine: 57,
                keywordType: 'Outcome',
                textWithKeyword:
                    'And I should not see "Attacker Name" in the "Users Projection" table',
                stepMatchArguments: [
                    {
                        group: {
                            start: 17,
                            value: '"Attacker Name"',
                            children: [
                                { start: 18, value: 'Attacker Name', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 40,
                            value: '"Users Projection"',
                            children: [
                                {
                                    start: 41,
                                    value: 'Users Projection',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 51,
                gherkinStepLine: 58,
                keywordType: 'Outcome',
                textWithKeyword:
                    'And I should see "Attacker Name" in the "Bookings Projection" table',
                stepMatchArguments: [
                    {
                        group: {
                            start: 13,
                            value: '"Attacker Name"',
                            children: [
                                { start: 14, value: 'Attacker Name', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 36,
                            value: '"Bookings Projection"',
                            children: [
                                {
                                    start: 37,
                                    value: 'Bookings Projection',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            }
        ]
    },
    {
        pwTestLine: 54,
        pickleLine: 60,
        tags: ['@reset'],
        steps: [
            {
                pwStepLine: 55,
                gherkinStepLine: 61,
                keywordType: 'Context',
                textWithKeyword: 'Given I am on the "/wizard" page',
                stepMatchArguments: [
                    {
                        group: {
                            start: 12,
                            value: '"/wizard"',
                            children: [
                                { start: 13, value: '/wizard', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 56,
                gherkinStepLine: 62,
                keywordType: 'Action',
                textWithKeyword: 'When I fill in "Client Name" with "Rebuild Test"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 10,
                            value: '"Client Name"',
                            children: [
                                { start: 11, value: 'Client Name', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 29,
                            value: '"Rebuild Test"',
                            children: [
                                { start: 30, value: 'Rebuild Test', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 57,
                gherkinStepLine: 63,
                keywordType: 'Action',
                textWithKeyword: 'And I fill in "Email Address" with "rebuild@test.com"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 10,
                            value: '"Email Address"',
                            children: [
                                { start: 11, value: 'Email Address', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 31,
                            value: '"rebuild@test.com"',
                            children: [
                                {
                                    start: 32,
                                    value: 'rebuild@test.com',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 58,
                gherkinStepLine: 64,
                keywordType: 'Action',
                textWithKeyword: 'And I click the "Submit Booking" button',
                stepMatchArguments: [
                    {
                        group: {
                            start: 12,
                            value: '"Submit Booking"',
                            children: [
                                {
                                    start: 13,
                                    value: 'Submit Booking',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 59,
                gherkinStepLine: 65,
                keywordType: 'Outcome',
                textWithKeyword: 'Then I should see "Request Received"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 13,
                            value: '"Request Received"',
                            children: [
                                {
                                    start: 14,
                                    value: 'Request Received',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 60,
                gherkinStepLine: 67,
                keywordType: 'Action',
                textWithKeyword: 'When I navigate to "/demo"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 14,
                            value: '"/demo"',
                            children: [
                                { start: 15, value: '/demo', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 61,
                gherkinStepLine: 68,
                keywordType: 'Action',
                textWithKeyword: 'And I click the "Reset Lab" button',
                stepMatchArguments: [
                    {
                        group: {
                            start: 12,
                            value: '"Reset Lab"',
                            children: [
                                { start: 13, value: 'Reset Lab', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 62,
                gherkinStepLine: 69,
                keywordType: 'Action',
                textWithKeyword: 'And I click the "Execute Reset" button',
                stepMatchArguments: [
                    {
                        group: {
                            start: 12,
                            value: '"Execute Reset"',
                            children: [
                                { start: 13, value: 'Execute Reset', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 63,
                gherkinStepLine: 70,
                keywordType: 'Outcome',
                textWithKeyword: 'Then I should see "0" in the "Historical Facts" counter',
                stepMatchArguments: [
                    {
                        group: {
                            start: 13,
                            value: '"0"',
                            children: [
                                { start: 14, value: '0', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 24,
                            value: '"Historical Facts"',
                            children: [
                                {
                                    start: 25,
                                    value: 'Historical Facts',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 64,
                gherkinStepLine: 73,
                keywordType: 'Action',
                textWithKeyword: 'When I navigate to "/wizard"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 14,
                            value: '"/wizard"',
                            children: [
                                { start: 15, value: '/wizard', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 65,
                gherkinStepLine: 74,
                keywordType: 'Action',
                textWithKeyword: 'And I fill in "Client Name" with "Persistent Fact"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 10,
                            value: '"Client Name"',
                            children: [
                                { start: 11, value: 'Client Name', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 29,
                            value: '"Persistent Fact"',
                            children: [
                                {
                                    start: 30,
                                    value: 'Persistent Fact',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 66,
                gherkinStepLine: 75,
                keywordType: 'Action',
                textWithKeyword: 'And I fill in "Email Address" with "persistent@test.com"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 10,
                            value: '"Email Address"',
                            children: [
                                { start: 11, value: 'Email Address', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 31,
                            value: '"persistent@test.com"',
                            children: [
                                {
                                    start: 32,
                                    value: 'persistent@test.com',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 67,
                gherkinStepLine: 76,
                keywordType: 'Action',
                textWithKeyword: 'And I click the "Submit Booking" button',
                stepMatchArguments: [
                    {
                        group: {
                            start: 12,
                            value: '"Submit Booking"',
                            children: [
                                {
                                    start: 13,
                                    value: 'Submit Booking',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 68,
                gherkinStepLine: 78,
                keywordType: 'Action',
                textWithKeyword: 'When I navigate to "/demo"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 14,
                            value: '"/demo"',
                            children: [
                                { start: 15, value: '/demo', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 69,
                gherkinStepLine: 79,
                keywordType: 'Action',
                textWithKeyword: 'And I click the "User Projection" button',
                stepMatchArguments: [
                    {
                        group: {
                            start: 12,
                            value: '"User Projection"',
                            children: [
                                {
                                    start: 13,
                                    value: 'User Projection',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 70,
                gherkinStepLine: 80,
                keywordType: 'Outcome',
                textWithKeyword: 'Then I should see "OFFLINE"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 13,
                            value: '"OFFLINE"',
                            children: [
                                { start: 14, value: 'OFFLINE', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 71,
                gherkinStepLine: 82,
                keywordType: 'Action',
                textWithKeyword: 'When I navigate to "/wizard"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 14,
                            value: '"/wizard"',
                            children: [
                                { start: 15, value: '/wizard', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 72,
                gherkinStepLine: 83,
                keywordType: 'Action',
                textWithKeyword: 'And I fill in "Client Name" with "Missed Projection"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 10,
                            value: '"Client Name"',
                            children: [
                                { start: 11, value: 'Client Name', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 29,
                            value: '"Missed Projection"',
                            children: [
                                {
                                    start: 30,
                                    value: 'Missed Projection',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 73,
                gherkinStepLine: 84,
                keywordType: 'Action',
                textWithKeyword: 'And I fill in "Email Address" with "missed@test.com"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 10,
                            value: '"Email Address"',
                            children: [
                                { start: 11, value: 'Email Address', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 31,
                            value: '"missed@test.com"',
                            children: [
                                {
                                    start: 32,
                                    value: 'missed@test.com',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 74,
                gherkinStepLine: 85,
                keywordType: 'Action',
                textWithKeyword: 'And I click the "Submit Booking" button',
                stepMatchArguments: [
                    {
                        group: {
                            start: 12,
                            value: '"Submit Booking"',
                            children: [
                                {
                                    start: 13,
                                    value: 'Submit Booking',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 75,
                gherkinStepLine: 87,
                keywordType: 'Action',
                textWithKeyword: 'When I navigate to "/demo"',
                stepMatchArguments: [
                    {
                        group: {
                            start: 14,
                            value: '"/demo"',
                            children: [
                                { start: 15, value: '/demo', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 76,
                gherkinStepLine: 88,
                keywordType: 'Outcome',
                textWithKeyword: 'Then I should see "2" in the "Historical Facts" counter',
                stepMatchArguments: [
                    {
                        group: {
                            start: 13,
                            value: '"2"',
                            children: [
                                { start: 14, value: '2', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 24,
                            value: '"Historical Facts"',
                            children: [
                                {
                                    start: 25,
                                    value: 'Historical Facts',
                                    children: [{ children: [] }]
                                },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 77,
                gherkinStepLine: 89,
                keywordType: 'Outcome',
                textWithKeyword: 'And I should see "1" in the "User Records" counter',
                stepMatchArguments: [
                    {
                        group: {
                            start: 13,
                            value: '"1"',
                            children: [
                                { start: 14, value: '1', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 24,
                            value: '"User Records"',
                            children: [
                                { start: 25, value: 'User Records', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 78,
                gherkinStepLine: 90,
                keywordType: 'Outcome',
                textWithKeyword: 'And I click the "Repair & Sync" button',
                stepMatchArguments: [
                    {
                        group: {
                            start: 12,
                            value: '"Repair & Sync"',
                            children: [
                                { start: 13, value: 'Repair & Sync', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            },
            {
                pwStepLine: 79,
                gherkinStepLine: 91,
                keywordType: 'Outcome',
                textWithKeyword: 'Then I should see "2" in the "User Records" counter',
                stepMatchArguments: [
                    {
                        group: {
                            start: 13,
                            value: '"2"',
                            children: [
                                { start: 14, value: '2', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    },
                    {
                        group: {
                            start: 24,
                            value: '"User Records"',
                            children: [
                                { start: 25, value: 'User Records', children: [{ children: [] }] },
                                { children: [{ children: [] }] }
                            ]
                        },
                        parameterTypeName: 'string'
                    }
                ]
            }
        ]
    }
]; // bdd-data-end
