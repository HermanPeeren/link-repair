import { defineConfig } from 'cypress';

/**
 * Cypress runs against a real Joomla with the plugin installed: whether the
 * Task Scheduler offers the task type and renders its form is something only
 * the scheduler itself can answer.
 *
 * The site and the login come from `cypress.env.json`, which is git-ignored.
 * Copy `cypress.env.json.dist` and fill it in.
 */
export default defineConfig({
  e2e: {
    // Overridden by `baseUrl` in cypress.env.json.
    baseUrl: 'http://localhost/link-repair/joomla',
    supportFile: 'tests/cypress/support/e2e.js',
    specPattern: 'tests/cypress/e2e/**/*.cy.js',
    video: false,
    screenshotOnRunFailure: true,
    screenshotsFolder: 'tests/cypress/screenshots',
    downloadsFolder: 'tests/cypress/downloads',

    setupNodeEvents(on, config) {
      if (config.env.baseUrl) {
        config.baseUrl = config.env.baseUrl;
      }

      if (!config.env.adminUser || !config.env.adminPassword) {
        throw new Error(
          'cypress.env.json needs adminUser and adminPassword. Copy cypress.env.json.dist and fill it in.',
        );
      }

      return config;
    },
  },
});
