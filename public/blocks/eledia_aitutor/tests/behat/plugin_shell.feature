@block @block_eledia_aitutor
Feature: eLeDia.ai Tutor plugin shell
  In order to administer the tutor consistently
  As a site administrator
  I need the tutor dashboard, library and preview pages to stay inside the plugin shell

  Background:
    Given the following config values are set as admin:
      | ragserverurl | https://rag.example.com/mcp | block_eledia_aitutor |
      | mcpserviceid | 1                           | block_eledia_aitutor |
    And I log in as "admin"

  Scenario: Dashboard opens in the plugin shell without Moodle block regions
    When I visit "/blocks/eledia_aitutor/configuration.php"
    Then I should see "eLeDia.ai Tutor"
    And I should see "Dashboard" in the ".lh-plugin-section-nav" "css_element"
    And I should see "Settings" in the ".lh-plugin-section-nav" "css_element"
    And I should see "Tutors" in the ".lh-plugin-section-nav" "css_element"
    And I should see "Preview" in the ".lh-plugin-section-nav" "css_element"
    And I should see "Setup wizard"
    And I should see "LiteRAG backend"
    And I should see "RagIngest"
    And I should see "MCP"
    And "#block-region-side-pre" "css_element" should not exist
    And "#theme_boost-drawers-blocks" "css_element" should not exist

  Scenario: Missing add-on plugins are announced once above the setup wizard
    When I visit "/blocks/eledia_aitutor/configuration.php"
    Then I should see "Add-on plugins missing"
    And I should see "Please install the missing add-on plugins eLeDia LiteRAG, eLeDia.ai RagIngest and eLeDia MCP."
    And I should see "Plugin missing"

  Scenario: Operator settings open inside the plugin shell without Moodle block regions
    When I visit "/blocks/eledia_aitutor/operator_settings.php"
    Then I should see "eLeDia.ai Tutor"
    And I should see "Settings" in the ".lh-plugin-section-nav" "css_element"
    And I should see "Design"
    And I should see "Conversation & display"
    And I should see "Technical settings"
    And "#block-region-side-pre" "css_element" should not exist
    And "#theme_boost-drawers-blocks" "css_element" should not exist

  Scenario: Help opens from the plugin-owned help page
    When I visit "/blocks/eledia_aitutor/help.php"
    Then I should see "eLeDia.ai Tutor"
    And I should see "Help"
    And I should see "User and administration documentation"
    And "#block-region-side-pre" "css_element" should not exist
    And "#theme_boost-drawers-blocks" "css_element" should not exist

  Scenario: Tutor library opens in the plugin shell with action icons
    When I visit "/blocks/eledia_aitutor/manage_tutors.php"
    Then I should see "eLeDia.ai Tutor"
    And I should see "Site tutors"
    And I should see "Tutors" in the ".lh-plugin-section-nav" "css_element"
    And ".eat-section-actions .lh-icon-action[aria-label='Create tutor']" "css_element" should exist
    And ".eat-section-actions .lh-icon-action[aria-label='Import']" "css_element" should exist
    And "#block-region-side-pre" "css_element" should not exist
    And "#theme_boost-drawers-blocks" "css_element" should not exist

  @javascript
  Scenario: Preview opens in the plugin shell without Moodle block regions
    When I visit "/blocks/eledia_aitutor/view.php"
    Then I should see "eLeDia.ai Tutor"
    And I should see "Preview" in the ".lh-plugin-section-nav" "css_element"
    And "#block-region-side-pre" "css_element" should not exist
    And "#theme_boost-drawers-blocks" "css_element" should not exist
