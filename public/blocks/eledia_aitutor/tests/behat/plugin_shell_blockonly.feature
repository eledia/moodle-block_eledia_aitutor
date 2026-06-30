# Block-only deployment scenarios.
#
# These assert behaviour that is only observable when the tutor block is installed
# WITHOUT its optional sibling plugins (local_literag, local_ragingest,
# webservice_elediamcp) — the layout the GitLab CI builds via publish.sh, which
# mounts the block alone into a clean Moodle.
#
# They are tagged @eat_blockonly and deliberately NOT @block_eledia_aitutor, so the
# normal in-tree run (`--tags @block_eledia_aitutor`, where the siblings ARE present)
# skips them — there they would fail, because the dashboard correctly does not warn
# about add-ons that are installed. The CI runs the behat directory by path and so
# still exercises them. The in-tree counterpart lives in plugin_shell.feature
# (scenario "No missing-add-on warning when the integration plugins are installed").
@eat_blockonly
Feature: eLeDia.ai Tutor plugin shell (block-only deployment)
  In order to be guided when the tutor's add-on plugins are not installed
  As a site administrator
  I need the dashboard to announce the missing add-on plugins

  Background:
    Given the following config values are set as admin:
      | ragserverurl | https://rag.example.com/mcp | block_eledia_aitutor |
      | mcpserviceid | 1                           | block_eledia_aitutor |
    And I log in as "admin"

  Scenario: Missing add-on plugins are announced once above the setup wizard
    When I visit "/blocks/eledia_aitutor/configuration.php"
    Then I should see "Add-on plugins missing"
    And I should see "Please install the missing add-on plugins eLeDia LiteRAG, eLeDia.ai RagIngest and eLeDia MCP."
    And I should see "Plugin missing"
