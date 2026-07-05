<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Global admin settings for the eLeDia.ai Tutor block.
 *
 * The infrastructure/security settings (RAG server, MCP token, limits, privacy)
 * are declared by hand. Everything that defines a *tutor* — persona, every visual
 * `--eat-*` token, launcher, footer, images and a few behaviour toggles — is
 * generated from {@see \block_eledia_aitutor\local\registry}, each followed by an
 * "allow per-instance override" checkbox (expose_<key>).
 *
 * @package     block_eledia_aitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use block_eledia_aitutor\local\branding;
use block_eledia_aitutor\local\registry;
use block_eledia_aitutor\output\shell;

if ($hassiteconfig) {
    require_once(__DIR__ . '/classes/local/security.php');
    require_once(__DIR__ . '/classes/local/registry.php');
    require_once(__DIR__ . '/classes/local/branding.php');
    require_once(__DIR__ . '/classes/output/shell.php');

    $currentsection = optional_param('section', '', PARAM_ALPHANUMEXT);
    // Guard the $PAGE->url read: while the admin tree is built during install/upgrade
    // (admin_apply_default_settings) no URL is set yet, and reading it would emit a
    // "did not call $PAGE->set_url()" debugging notice — which moodle-plugin-ci treats
    // as a failure. has_set_url() is false there, so we simply skip the decoration.
    $decoratecoresettingspage = $PAGE->has_set_url()
        && $PAGE->url->get_path() === '/' . $CFG->admin . '/settings.php';
    if ($ADMIN->fulltree && $currentsection === 'blocksettingeledia_aitutor' && $decoratecoresettingspage) {
        global $OUTPUT, $PAGE;
        shell::require_css();

        if (shell::is_available()) {
            $PAGE->add_body_class('eat-admin-settings-pending');
            $sectioncards = [
                [
                    'key' => 'design',
                    'icon' => 'palette',
                    'title' => get_string('setting_section_design', 'block_eledia_aitutor'),
                    'body' => get_string('setting_section_design_desc', 'block_eledia_aitutor'),
                ],
                [
                    'key' => 'conversation',
                    'icon' => 'comments',
                    'title' => get_string('setting_section_conversation', 'block_eledia_aitutor'),
                    'body' => get_string('setting_section_conversation_desc', 'block_eledia_aitutor'),
                ],
                [
                    'key' => 'technical',
                    'icon' => 'plug',
                    'title' => get_string('setting_section_technical', 'block_eledia_aitutor'),
                    'body' => get_string('setting_section_technical_desc', 'block_eledia_aitutor'),
                ],
            ];
            $headerhtml = $OUTPUT->render_from_template(
                'block_eledia_aitutor/plugin_shell_header',
                shell::context(shell::ACTIVE_SETTINGS, true)
            );
            // The shell config carries a rendered header template (~2 KB), which exceeds the
            // 1024-char budget js_call_amd warns about (and dev debugging escalates to a fatal).
            // Invoke the same module via an inline require() instead, so the payload travels in
            // an inline <script> rather than the AMD argument string. The init() contract is
            // unchanged. JSON_HEX_TAG keeps any markup in headerHtml from breaking the script.
            $shellconfig = [
                'headerHtml' => $headerhtml,
                'sectionCards' => $sectioncards,
                'pluginTitle' => get_string('pluginname', 'block_eledia_aitutor'),
                'hubTitle' => get_string('settings_hub_title', 'block_eledia_aitutor'),
                'hubDesc' => get_string('settings_hub_desc', 'block_eledia_aitutor'),
                'topicsLabel' => get_string('settings_hub_topics_label', 'block_eledia_aitutor'),
                'backLabel' => get_string('settings_back_to_overview', 'block_eledia_aitutor'),
                'exposeLabel' => get_string('settings_expose_inline_label', 'block_eledia_aitutor'),
                'cancelLabel' => get_string('cancel'),
                'cancelUrl' => (new moodle_url('/blocks/eledia_aitutor/configuration.php'))->out(false),
            ];
            $encodedconfig = json_encode(
                $shellconfig,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
            );
            $PAGE->requires->js_amd_inline(
                "require(['block_eledia_aitutor/settings_shell'], function(shell) {"
                . "shell.init({$encodedconfig});"
                . "});"
            );
        }
    }

                // Shell-friendly configuration overview.
    $ADMIN->add('blocksettings', new admin_externalpage(
        'block_eledia_aitutor_configuration',
        get_string('configuration', 'block_eledia_aitutor'),
        new moodle_url('/blocks/eledia_aitutor/configuration.php'),
        'moodle/site:config'
    ));

    // The tutor library / import-export management page.
    $ADMIN->add('blocksettings', new admin_externalpage(
        'block_eledia_aitutor_managetutors',
        get_string('managetutors', 'block_eledia_aitutor'),
        new moodle_url('/blocks/eledia_aitutor/manage_tutors.php'),
        'moodle/site:config'
    ));

    // Offer the MCP services declared by webservice_elediamcp when available,
    // else fall back to all enabled external services.
    $serviceoptions = [0 => get_string('setting_mcpserviceid_none', 'block_eledia_aitutor')];
    if (during_initial_install() === false) {
        $mcpapi = '\\webservice_elediamcp\\api';
        if (class_exists($mcpapi)) {
            foreach (call_user_func([$mcpapi, 'get_services']) as $svc) {
                $serviceoptions[(int) $svc->id] = format_string($svc->name);
            }
        } else {
            global $DB;
            $services = $DB->get_records('external_services', ['enabled' => 1], 'name ASC', 'id, name');
            foreach ($services as $svc) {
                $serviceoptions[(int) $svc->id] = format_string($svc->name);
            }
        }
    }

    // Friendly label/description with a sensible fallback for the ~40 raw tokens.
    $reglabel = function (string $key, array $entry): string {
        if (get_string_manager()->string_exists('reg_' . $key, 'block_eledia_aitutor')) {
            return get_string('reg_' . $key, 'block_eledia_aitutor');
        }
        return (string) ($entry['token'] ?? $key);
    };
    $regdesc = function (string $key): string {
        if (get_string_manager()->string_exists('reg_' . $key . '_desc', 'block_eledia_aitutor')) {
            return get_string('reg_' . $key . '_desc', 'block_eledia_aitutor');
        }
        return get_string('reg_tokenhint', 'block_eledia_aitutor');
    };
    // Registry key => [storedfile config name, file area] for the image settings.
    $filemap = [
        'logo' => ['brandlogo', branding::LOGO_FILEAREA],
        'avatar' => ['brandavatar', branding::AVATAR_FILEAREA],
    ];
    $imageopts = ['maxfiles' => 1, 'accepted_types' => ['.png', '.jpg', '.jpeg', '.svg', '.webp', '.gif']];

    $infocards = static function (array $cards): string {
        $html = html_writer::start_div('eat-settings-infocards');
        foreach ($cards as $card) {
            $html .= html_writer::div(
                html_writer::span(
                    \block_eledia_aitutor\local\icon::render($card['icon']),
                    'eat-settings-infocard__icon'
                ) .
                html_writer::div(
                    html_writer::div($card['title'], 'eat-settings-infocard__title') .
                    html_writer::div($card['body'], 'eat-settings-infocard__body'),
                    'eat-settings-infocard__text'
                ),
                'eat-settings-infocard'
            );
        }
        return $html . html_writer::end_div();
    };

    $addregistrygroup = function (string $group) use ($settings, $reglabel, $regdesc, $filemap, $imageopts): void {
        $keys = array_values(array_filter(
            registry::group_keys($group),
            static fn(string $key): bool => registry::is_available($key)
        ));
        if (empty($keys)) {
            return;
        }
        $settings->add(new admin_setting_heading(
            'block_eledia_aitutor/reggroup_' . $group,
            get_string('reggroup_' . $group, 'block_eledia_aitutor'),
            ''
        ));

        foreach ($keys as $key) {
            $entry = registry::get($key);
            $label = $reglabel($key, $entry);
            $desc = $regdesc($key);
            $default = $entry['default'];

            if ($entry['type'] === 'file') {
                [$cfgname, $filearea] = $filemap[$key];
                $settings->add(new admin_setting_configstoredfile(
                    'block_eledia_aitutor/' . $cfgname,
                    $label,
                    $desc,
                    $filearea,
                    0,
                    $imageopts
                ));
            } else if ($entry['type'] === 'colour') {
                $settings->add(new admin_setting_configcolourpicker(
                    'block_eledia_aitutor/' . registry::sitekey($key),
                    $label,
                    $desc,
                    (string) $default
                ));
            } else if ($entry['type'] === 'checkbox') {
                $settings->add(new admin_setting_configcheckbox(
                    'block_eledia_aitutor/' . registry::sitekey($key),
                    $label,
                    $desc,
                    (int) $default
                ));
            } else if ($entry['type'] === 'select') {
                $options = [];
                foreach ($entry['options'] as $value => $optkey) {
                    $options[$value] = get_string($optkey, 'block_eledia_aitutor');
                }
                $settings->add(new admin_setting_configselect(
                    'block_eledia_aitutor/' . registry::sitekey($key),
                    $label,
                    $desc,
                    (string) $default,
                    $options
                ));
            } else if ($entry['type'] === 'textarea') {
                $settings->add(new admin_setting_configtextarea(
                    'block_eledia_aitutor/' . registry::sitekey($key),
                    $label,
                    $desc,
                    (string) $default
                ));
            } else if (!empty($entry['choices'])) {
                // Cssvalue / font with friendly named options → a dropdown so no
                // one has to type raw CSS. The empty value is the built-in default.
                $cfgname = registry::sitekey($key);
                $current = get_config('block_eledia_aitutor', $cfgname);
                $options = registry::choice_select_options(
                    $key,
                    get_string('reg_opt_default', 'block_eledia_aitutor'),
                    $current === false ? null : (string) $current
                );
                $settings->add(new admin_setting_configselect(
                    'block_eledia_aitutor/' . $cfgname,
                    $label,
                    $desc,
                    (string) $default,
                    $options
                ));
            } else {
                // Free text (persona fields, labels, footer text).
                $settings->add(new admin_setting_configtext(
                    'block_eledia_aitutor/' . registry::sitekey($key),
                    $label,
                    $desc,
                    (string) $default,
                    PARAM_TEXT
                ));
            }

            // The "Allow per-instance override" companion checkbox.
            if (!empty($entry['instanceable'])) {
                $settings->add(new admin_setting_configcheckbox(
                    'block_eledia_aitutor/' . registry::EXPOSE_PREFIX . $key,
                    get_string('expose_label', 'block_eledia_aitutor', $label),
                    get_string('expose_desc', 'block_eledia_aitutor'),
                    !empty($entry['exposedefault']) ? 1 : 0
                ));
            }
        }
    };

    // Design.
    $settings->add(new admin_setting_heading(
        'block_eledia_aitutor/sectiondesign',
        get_string('setting_section_design', 'block_eledia_aitutor'),
        ''
    ));

    foreach (['accent', 'surfaces', 'text', 'bubbles', 'states', 'shape', 'effects', 'launcher', 'footer', 'files'] as $group) {
        $addregistrygroup($group);
    }

    // Admin custom CSS (trusted; targets the widget's .eledia_aitutor-* classes).
    $settings->add(new admin_setting_configtextarea(
        'block_eledia_aitutor/customcss',
        get_string('setting_customcss', 'block_eledia_aitutor'),
        get_string('setting_customcss_desc', 'block_eledia_aitutor'),
        '',
        PARAM_RAW
    ));

    // Conversation & display.
    $settings->add(new admin_setting_heading(
        'block_eledia_aitutor/sectionconversation',
        get_string('setting_section_conversation', 'block_eledia_aitutor'),
        ''
    ));

    $settings->add(new admin_setting_heading(
        'block_eledia_aitutor/headerchatactivation',
        get_string('setting_header_chat_activation', 'block_eledia_aitutor'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_eledia_aitutor/enableglobalchat',
        get_string('setting_enableglobalchat', 'block_eledia_aitutor'),
        get_string('setting_enableglobalchat_desc', 'block_eledia_aitutor'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_eledia_aitutor/enablecoursechat',
        get_string('setting_enablecoursechat', 'block_eledia_aitutor'),
        get_string('setting_enablecoursechat_desc', 'block_eledia_aitutor'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_eledia_aitutor/enablesitewidechat',
        get_string('setting_enablesitewidechat', 'block_eledia_aitutor'),
        get_string('setting_enablesitewidechat_desc', 'block_eledia_aitutor'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_eledia_aitutor/allowllmonly',
        get_string('setting_allowllmonly', 'block_eledia_aitutor'),
        get_string('setting_allowllmonly_desc', 'block_eledia_aitutor'),
        1
    ));

    foreach (['persona', 'conversation', 'dashboard'] as $group) {
        $addregistrygroup($group);
    }

    // AI-Home: pointer to the full-page start experience and how to make it
    // the site's landing page (defaulthomepage → custom URL, Moodle 4.2+).
    $settings->add(new admin_setting_heading(
        'block_eledia_aitutor/headerhome',
        get_string('setting_header_home', 'block_eledia_aitutor'),
        get_string(
            'setting_header_home_desc',
            'block_eledia_aitutor',
            (new moodle_url('/blocks/eledia_aitutor/home.php'))->out(false)
        )
    ));

    $settings->add(new admin_setting_heading(
        'block_eledia_aitutor/headerprivacy',
        get_string('setting_header_privacy', 'block_eledia_aitutor'),
        ''
    ));

    $settings->add(new admin_setting_confightmleditor(
        'block_eledia_aitutor/privacyguidelinestext',
        get_string('setting_privacyguidelinestext', 'block_eledia_aitutor'),
        get_string('setting_privacyguidelinestext_desc', 'block_eledia_aitutor'),
        ''
    ));

    // Technical settings.
    $settings->add(new admin_setting_heading(
        'block_eledia_aitutor/sectiontechnical',
        get_string('setting_section_technical', 'block_eledia_aitutor'),
        ''
    ));

    $settings->add(new admin_setting_heading(
        'block_eledia_aitutor/headerrag',
        get_string('setting_header_rag', 'block_eledia_aitutor'),
        get_string('setting_header_rag_desc', 'block_eledia_aitutor')
    ));

    $settings->add(new admin_setting_configtext(
        'block_eledia_aitutor/ragserverurl',
        get_string('setting_ragserverurl', 'block_eledia_aitutor'),
        get_string('setting_ragserverurl_desc', 'block_eledia_aitutor'),
        '',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configselect(
        'block_eledia_aitutor/ragauthmethod',
        get_string('setting_ragauthmethod', 'block_eledia_aitutor'),
        get_string('setting_ragauthmethod_desc', 'block_eledia_aitutor'),
        'none',
        [
            'none' => get_string('authmethod_none', 'block_eledia_aitutor'),
            'bearer' => get_string('authmethod_bearer', 'block_eledia_aitutor'),
            'header' => get_string('authmethod_header', 'block_eledia_aitutor'),
        ]
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'block_eledia_aitutor/ragauthtoken',
        get_string('setting_ragauthtoken', 'block_eledia_aitutor'),
        get_string('setting_ragauthtoken_desc', 'block_eledia_aitutor'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'block_eledia_aitutor/chattoolname',
        get_string('setting_chattoolname', 'block_eledia_aitutor'),
        get_string('setting_chattoolname_desc', 'block_eledia_aitutor'),
        'tutor_chat',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'block_eledia_aitutor/historytoolname',
        get_string('setting_historytoolname', 'block_eledia_aitutor'),
        get_string('setting_historytoolname_desc', 'block_eledia_aitutor'),
        '',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'block_eledia_aitutor/deletetoolname',
        get_string('setting_deletetoolname', 'block_eledia_aitutor'),
        get_string('setting_deletetoolname_desc', 'block_eledia_aitutor'),
        '',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'block_eledia_aitutor/deleteusertoolname',
        get_string('setting_deleteusertoolname', 'block_eledia_aitutor'),
        get_string('setting_deleteusertoolname_desc', 'block_eledia_aitutor'),
        '',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'block_eledia_aitutor/memoryoptintoolname',
        get_string('setting_memoryoptintoolname', 'block_eledia_aitutor'),
        get_string('setting_memoryoptintoolname_desc', 'block_eledia_aitutor'),
        '',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_eledia_aitutor/allowinsecuretransport',
        get_string('setting_allowinsecuretransport', 'block_eledia_aitutor'),
        get_string('setting_allowinsecuretransport_desc', 'block_eledia_aitutor'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_eledia_aitutor/allowprivatenetwork',
        get_string('setting_allowprivatenetwork', 'block_eledia_aitutor'),
        get_string('setting_allowprivatenetwork_desc', 'block_eledia_aitutor'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'block_eledia_aitutor/requesttimeout',
        get_string('setting_requesttimeout', 'block_eledia_aitutor'),
        get_string('setting_requesttimeout_desc', 'block_eledia_aitutor'),
        30,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_eledia_aitutor/streamingenabled',
        get_string('setting_streamingenabled', 'block_eledia_aitutor'),
        get_string('setting_streamingenabled_desc', 'block_eledia_aitutor'),
        0
    ));

    $settings->add(new admin_setting_heading(
        'block_eledia_aitutor/headertoken',
        get_string('setting_header_token', 'block_eledia_aitutor'),
        get_string('setting_header_token_desc', 'block_eledia_aitutor')
    ));

    $settings->add(new admin_setting_configselect(
        'block_eledia_aitutor/mcpserviceid',
        get_string('setting_mcpserviceid', 'block_eledia_aitutor'),
        get_string('setting_mcpserviceid_desc', 'block_eledia_aitutor'),
        0,
        $serviceoptions
    ));

    $settings->add(new admin_setting_configtext(
        'block_eledia_aitutor/tokenlifetime',
        get_string('setting_tokenlifetime', 'block_eledia_aitutor'),
        get_string('setting_tokenlifetime_desc', 'block_eledia_aitutor'),
        3600,
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        'block_eledia_aitutor/headerbehaviour',
        get_string('setting_header_behaviour', 'block_eledia_aitutor'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'block_eledia_aitutor/maxmessagelength',
        get_string('setting_maxmessagelength', 'block_eledia_aitutor'),
        get_string('setting_maxmessagelength_desc', 'block_eledia_aitutor'),
        4000,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_eledia_aitutor/ratelimitperminute',
        get_string('setting_ratelimitperminute', 'block_eledia_aitutor'),
        get_string('setting_ratelimitperminute_desc', 'block_eledia_aitutor'),
        20,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_eledia_aitutor/dailymessagelimit',
        get_string('setting_dailymessagelimit', 'block_eledia_aitutor'),
        get_string('setting_dailymessagelimit_desc', 'block_eledia_aitutor'),
        0,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_eledia_aitutor/enableanalytics',
        get_string('setting_enableanalytics', 'block_eledia_aitutor'),
        get_string('setting_enableanalytics_desc', 'block_eledia_aitutor'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'block_eledia_aitutor/analyticsretentiondays',
        get_string('setting_analyticsretention', 'block_eledia_aitutor'),
        get_string('setting_analyticsretention_desc', 'block_eledia_aitutor'),
        180,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_eledia_aitutor/reclustertoolname',
        get_string('setting_reclustertoolname', 'block_eledia_aitutor'),
        get_string('setting_reclustertoolname_desc', 'block_eledia_aitutor'),
        '',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configselect(
        'block_eledia_aitutor/loggingverbosity',
        get_string('setting_loggingverbosity', 'block_eledia_aitutor'),
        get_string('setting_loggingverbosity_desc', 'block_eledia_aitutor'),
        1,
        [
            0 => get_string('loglevel_errors', 'block_eledia_aitutor'),
            1 => get_string('loglevel_normal', 'block_eledia_aitutor'),
            2 => get_string('loglevel_verbose', 'block_eledia_aitutor'),
        ]
    ));
}
