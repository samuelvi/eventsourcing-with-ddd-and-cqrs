import { defineConfig, devices } from '@playwright/test';
import { defineBddConfig } from 'playwright-bdd';

/**
 * Playwright BDD Configuration Base
 *
 * This configuration is designed to be used as a starting point for E2E testing
 * with Playwright and BDD/Gherkin.
 */

const bddDir = defineBddConfig({
    paths: ['tests/e2e/**/*.feature'],
    require: ['tests/e2e/common/bdd.ts', 'tests/e2e/common/steps/*.ts', 'tests/e2e/**/*.steps.ts'],
    importTestFrom: 'tests/e2e/common/bdd.ts'
});

export default defineConfig({
    // BDD scenarios within a feature are sequential by design.
    // Parallelism is achieved at the feature file level.
    fullyParallel: false,
    workers: 1,

    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 1,

    reporter: [['html', { outputFolder: './var/log/playwright/report', open: 'never' }], ['list']],

    outputDir: './var/log/playwright/test-results',

    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:9080',
        actionTimeout: 15000,
        navigationTimeout: 30000,
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        trace: 'on-first-retry'
    },

    expect: {
        timeout: 30000
    },

    timeout: 120000,
    grepInvert: /@ignore/,

    projects: [
        {
            name: 'bdd',
            testDir: bddDir,
            use: { ...devices['Desktop Chrome'] }
        },
        {
            name: 'e2e',
            testDir: './tests/e2e',
            testMatch: '**/*.spec.ts',
            testIgnore: ['**/bdd-gen/**', '**/features/.gen/**'],
            use: { ...devices['Desktop Chrome'] }
        }
    ]
});
