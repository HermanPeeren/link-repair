/**
 * The Task Scheduler offers both task types, and their forms have every parameter
 * the routines read, with safe defaults.
 *
 * Running the tasks is checked by hand on the development site
 * (docs/development.md): it needs articles with links and menu items to match.
 */
describe('The link repair task types', () => {
  beforeEach(() => {
    cy.loginToAdmin();
  });

  it('are offered when creating a new task', () => {
    cy.visit('/administrator/index.php?option=com_scheduler&view=select');

    cy.contains('Link repair: scan').should('be.visible');
    cy.contains('Link repair: repair').should('be.visible');
  });

  it('scan has the site address, other hosts, redirects and time budget', () => {
    cy.visit('/administrator/index.php?option=com_scheduler&task=task.add&type=linkrepair.scan');

    cy.get('#jform_params_site_url').should('exist');
    cy.get('#jform_params_other_hosts').should('exist');
    cy.get('#jform_params_follow_redirects1').should('be.checked');
    cy.get('#jform_params_time_budget').should('have.value', '20');

    cy.get('#toolbar-cancel button, button.button-cancel').first().click();
  });

  it('repair starts as a dry run', () => {
    cy.visit('/administrator/index.php?option=com_scheduler&task=task.add&type=linkrepair.repair');

    // A new repair task changes nothing until dry run is switched off.
    cy.get('#jform_params_dry_run1').should('be.checked');
    cy.get('#jform_params_as_user_id').should('exist');
    cy.get('#jform_params_time_budget').should('have.value', '20');

    cy.get('#toolbar-cancel button, button.button-cancel').first().click();
  });
});
