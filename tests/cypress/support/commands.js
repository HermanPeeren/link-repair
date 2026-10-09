/**
 * Sign in to the administrator, once per spec run.
 *
 * The credentials come from `cypress.env.json`, which is git-ignored, and are
 * never written into a spec. `cy.session` caches the cookies, so the login
 * form is filled in once rather than before every test.
 */
Cypress.Commands.add('loginToAdmin', () => {
  const user = Cypress.env('adminUser');
  const password = Cypress.env('adminPassword');

  cy.session(
    ['joomla-admin', user],
    () => {
      cy.visit('/administrator/index.php');

      cy.get('#mod-login-username').type(user);
      cy.get('#mod-login-password').type(password, { log: false });
      cy.get('#btn-login-submit').click();

      cy.get('#sidebarmenu, .header', { timeout: 20000 }).should('exist');
    },
    {
      validate() {
        cy.request('/administrator/index.php').its('status').should('eq', 200);
      },
    },
  );
});
