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
 * generated from {@see \block_elediaaitutor\local\registry}, each followed by an
 * "allow per-instance override" checkbox (expose_<key>).
 *
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use block_elediaaitutor\local\branding;
use block_elediaaitutor\local\registry;
use block_elediaaitutor\output\shell;

if ($hassiteconfig) {
    require_once(__DIR__ . '/classes/local/security.php');
    require_once(__DIR__ . '/classes/local/registry.php');
    require_once(__DIR__ . '/classes/local/branding.php');
    require_once(__DIR__ . '/classes/output/shell.php');

    $currentsection = optional_param('section', '', PARAM_ALPHANUMEXT);
    if ($ADMIN->fulltree && $currentsection === 'blocksettingelediaaitutor') {
        global $OUTPUT, $PAGE;
        shell::require_css();

        if (shell::is_available()) {
            $PAGE->add_body_class('eat-admin-settings-pending');
            $sectioncards = [
                [
                    'key' => 'design',
                    'icon' => 'fa-palette',
                    'title' => get_string('setting_section_design', 'block_elediaaitutor'),
                    'body' => get_string('setting_section_design_desc', 'block_elediaaitutor'),
                ],
                [
                    'key' => 'conversation',
                    'icon' => 'fa-comments',
                    'title' => get_string('setting_section_conversation', 'block_elediaaitutor'),
                    'body' => get_string('setting_section_conversation_desc', 'block_elediaaitutor'),
                ],
                [
                    'key' => 'technical',
                    'icon' => 'fa-plug',
                    'title' => get_string('setting_section_technical', 'block_elediaaitutor'),
                    'body' => get_string('setting_section_technical_desc', 'block_elediaaitutor'),
                ],
            ];
            $headerhtml = $OUTPUT->render_from_template(
                'block_elediaaitutor/plugin_shell_header',
                shell::context(shell::ACTIVE_SETTINGS, true)
            );
            $PAGE->requires->js_call_amd('block_elediaaitutor/settings_shell', 'init', [[
                'headerHtml' => $headerhtml,
                'sectionCards' => $sectioncards,
                'pluginTitle' => get_string('pluginname', 'block_elediaaitutor'),
                'hubTitle' => get_string('settings_hub_title', 'block_elediaaitutor'),
                'hubDesc' => get_string('settings_hub_desc', 'block_elediaaitutor'),
                'topicsLabel' => get_string('settings_hub_topics_label', 'block_elediaaitutor'),
                'backLabel' => get_string('settings_back_to_overview', 'block_elediaaitutor'),
                'exposeLabel' => get_string('settings_expose_inline_label', 'block_elediaaitutor'),
                'cancelLabel' => get_string('cancel'),
                'cancelUrl' => (new moodle_url('/blocks/elediaaitutor/configuration.php'))->out(false),
            ]]);
        }
    }

    // Shell-friendly configuration overview for LernHive installations.
    $ADMIN->add('blocksettings', new admin_externalpage(
        'block_elediaaitutor_configuration',
        get_string('configuration', 'block_elediaaitutor'),
        new moodle_url('/blocks/elediaaitutor/configuration.php'),
        'moodle/site:config'
    ));

    // The tutor library / import-export management page.
    $ADMIN->add('blocksettings', new admin_externalpage(
        'block_elediaaitutor_managetutors',
        get_string('managetutors', 'block_elediaaitutor'),
        new moodle_url('/blocks/elediaaitutor/manage_tutors.php'),
        'moodle/site:config'
    ));

    // Offer the MCP services declared by webservice_elediamcp when available,
    // else fall back to all enabled external services.
    $serviceoptions = [0 => get_string('setting_mcpserviceid_none', 'block_elediaaitutor')];
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
        if (get_string_manager()->string_exists('reg_' . $key, 'block_elediaaitutor')) {
            return get_string('reg_' . $key, 'block_elediaaitutor');
        }
        return (string) ($entry['token'] ?? $key);
    };
    $regdesc = function (string $key): string {
        if (get_string_manager()->string_exists('reg_' . $key . '_desc', 'block_elediaaitutor')) {
            return get_string('reg_' . $key . '_desc', 'block_elediaaitutor');
        }
        return get_string('reg_tokenhint', 'block_elediaaitutor');
    };
    // Registry key => [storedfile config name, file area] for the image settings.
    $filemap = [
        'logo' => ['brandlogo', branding::LOGO_FILEAREA],
        'avatar' => ['brandavatar', branding::AVATAR_FILEAREA],
    ];
    $imageopts = ['maxfiles' => 1, 'accepted_types' => ['.png', '.jpg', '.jpeg', '.svg', '.webp', '.gif']];

    $infocards = static function(array $cards): string {
        $html = html_writer::start_div('eat-settings-infocards');
        foreach ($cards as $card) {
            $html .= html_writer::div(
                html_writer::span(
                    html_writer::tag('i', '', ['class' => 'fa fa-' . $card['icon'], 'aria-hidden' => 'true']),
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

    $addregistrygroup = function(string $group) use ($settings, $reglabel, $regdesc, $filemap, $imageopts): void {
        $keys = array_values(array_filter(registry::group_keys($group),
            static fn(string $key): bool => registry::is_available($key)));
        if (empty($keys)) {
            return;
        }
        $settings->add(new admin_setting_heading(
            'block_elediaaitutor/reggroup_' . $group,
            get_string('reggroup_' . $group, 'block_elediaaitutor'),
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
                    'block_elediaaitutor/' . $cfgname, $label, $desc, $filearea, 0, $imageopts));
            } else if ($entry['type'] === 'colour') {
                $settings->add(new admin_setting_configcolourpicker(
                    'block_elediaaitutor/' . registry::sitekey($key), $label, $desc, (string) $default));
            } else if ($entry['type'] === 'checkbox') {
                $settings->add(new admin_setting_configcheckbox(
                    'block_elediaaitutor/' . registry::sitekey($key), $label, $desc, (int) $default));
            } else if ($entry['type'] === 'select') {
                $options = [];
                foreach ($entry['options'] as $value => $optkey) {
                    $options[$value] = get_string($optkey, 'block_elediaaitutor');
                }
                $settings->add(new admin_setting_configselect(
                    'block_elediaaitutor/' . registry::sitekey($key), $label, $desc, (string) $default, $options));
            } else if ($entry['type'] === 'textarea') {
                $settings->add(new admin_setting_configtextarea(
                    'block_elediaaitutor/' . registry::sitekey($key), $label, $desc, (string) $default));
            } else if (!empty($entry['choices'])) {
                // cssvalue / font with friendly named options → a dropdown so no
                // one has to type raw CSS. The empty value is the built-in default.
                $cfgname = registry::sitekey($key);
                $current = get_config('block_elediaaitutor', $cfgname);
                $options = registry::choice_select_options($key,
                    get_string('reg_opt_default', 'block_elediaaitutor'),
                    $current === false ? null : (string) $current);
                $settings->add(new admin_setting_configselect(
                    'block_elediaaitutor/' . $cfgname, $label, $desc, (string) $default, $options));
            } else {
                // Free text (persona fields, labels, footer text).
                $settings->add(new admin_setting_configtext(
                    'block_elediaaitutor/' . registry::sitekey($key), $label, $desc, (string) $default, PARAM_TEXT));
            }

            // "Allow per-instance override" companion checkbox.
            if (!empty($entry['instanceable'])) {
                $settings->add(new admin_setting_configcheckbox(
                    'block_elediaaitutor/' . registry::EXPOSE_PREFIX . $key,
                    get_string('expose_label', 'block_elediaaitutor', $label),
                    get_string('expose_desc', 'block_elediaaitutor'),
                    !empty($entry['exposedefault']) ? 1 : 0
                ));
            }
        }
    };

    // --- Design. -----------------------------------------------------------.
    $settings->add(new admin_setting_heading(
        'block_elediaaitutor/sectiondesign',
        get_string('setting_section_design', 'block_elediaaitutor'),
        ''
    ));

    foreach (['accent', 'surfaces', 'text', 'bubbles', 'states', 'shape', 'effects', 'launcher', 'footer', 'files'] as $group) {
        $addregistrygroup($group);
    }

    // Admin custom CSS (trusted; targets the widget's .elediaaitutor-* classes).
    $settings->add(new admin_setting_configtextarea(
        'block_elediaaitutor/customcss',
        get_string('setting_customcss', 'block_elediaaitutor'),
        get_string('setting_customcss_desc', 'block_elediaaitutor'),
        '',
        PARAM_RAW
    ));

    // --- Conversation & display. ------------------------------------------.
    $settings->add(new admin_setting_heading(
        'block_elediaaitutor/sectionconversation',
        get_string('setting_section_conversation', 'block_elediaaitutor'),
        ''
    ));

    $settings->add(new admin_setting_heading(
        'block_elediaaitutor/headerchatactivation',
        get_string('setting_header_chat_activation', 'block_elediaaitutor'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_elediaaitutor/enableglobalchat',
        get_string('setting_enableglobalchat', 'block_elediaaitutor'),
        get_string('setting_enableglobalchat_desc', 'block_elediaaitutor'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_elediaaitutor/enablecoursechat',
        get_string('setting_enablecoursechat', 'block_elediaaitutor'),
        get_string('setting_enablecoursechat_desc', 'block_elediaaitutor'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_elediaaitutor/allowllmonly',
        get_string('setting_allowllmonly', 'block_elediaaitutor'),
        get_string('setting_allowllmonly_desc', 'block_elediaaitutor'),
        1
    ));

    foreach (['persona', 'conversation'] as $group) {
        $addregistrygroup($group);
    }

    $settings->add(new admin_setting_heading(
        'block_elediaaitutor/headerprivacy',
        get_string('setting_header_privacy', 'block_elediaaitutor'),
        ''
    ));

    $settings->add(new admin_setting_confightmleditor(
        'block_elediaaitutor/privacyguidelinestext',
        get_string('setting_privacyguidelinestext', 'block_elediaaitutor'),
        get_string('setting_privacyguidelinestext_desc', 'block_elediaaitutor'),
        ''
    ));

    // --- Technical settings. ----------------------------------------------.
    $settings->add(new admin_setting_heading(
        'block_elediaaitutor/sectiontechnical',
        get_string('setting_section_technical', 'block_elediaaitutor'),
        ''
    ));

    $settings->add(new admin_setting_heading(
        'block_elediaaitutor/headerrag',
        get_string('setting_header_rag', 'block_elediaaitutor'),
        get_string('setting_header_rag_desc', 'block_elediaaitutor')
    ));

    $settings->add(new admin_setting_configtext(
        'block_elediaaitutor/ragserverurl',
        get_string('setting_ragserverurl', 'block_elediaaitutor'),
        get_string('setting_ragserverurl_desc', 'block_elediaaitutor'),
        '',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configselect(
        'block_elediaaitutor/ragauthmethod',
        get_string('setting_ragauthmethod', 'block_elediaaitutor'),
        get_string('setting_ragauthmethod_desc', 'block_elediaaitutor'),
        'none',
        [
            'none' => get_string('authmethod_none', 'block_elediaaitutor'),
            'bearer' => get_string('authmethod_bearer', 'block_elediaaitutor'),
            'header' => get_string('authmethod_header', 'block_elediaaitutor'),
        ]
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'block_elediaaitutor/ragauthtoken',
        get_string('setting_ragauthtoken', 'block_elediaaitutor'),
        get_string('setting_ragauthtoken_desc', 'block_elediaaitutor'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'block_elediaaitutor/chattoolname',
        get_string('setting_chattoolname', 'block_elediaaitutor'),
        get_string('setting_chattoolname_desc', 'block_elediaaitutor'),
        'tutor_chat',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'block_elediaaitutor/historytoolname',
        get_string('setting_historytoolname', 'block_elediaaitutor'),
        get_string('setting_historytoolname_desc', 'block_elediaaitutor'),
        '',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'block_elediaaitutor/deletetoolname',
        get_string('setting_deletetoolname', 'block_elediaaitutor'),
        get_string('setting_deletetoolname_desc', 'block_elediaaitutor'),
        '',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'block_elediaaitutor/deleteusertoolname',
        get_string('setting_deleteusertoolname', 'block_elediaaitutor'),
        get_string('setting_deleteusertoolname_desc', 'block_elediaaitutor'),
        '',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'block_elediaaitutor/memoryoptintoolname',
        get_string('setting_memoryoptintoolname', 'block_elediaaitutor'),
        get_string('setting_memoryoptintoolname_desc', 'block_elediaaitutor'),
        '',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_elediaaitutor/allowinsecuretransport',
        get_string('setting_allowinsecuretransport', 'block_elediaaitutor'),
        get_string('setting_allowinsecuretransport_desc', 'block_elediaaitutor'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_elediaaitutor/allowprivatenetwork',
        get_string('setting_allowprivatenetwork', 'block_elediaaitutor'),
        get_string('setting_allowprivatenetwork_desc', 'block_elediaaitutor'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'block_elediaaitutor/requesttimeout',
        get_string('setting_requesttimeout', 'block_elediaaitutor'),
        get_string('setting_requesttimeout_desc', 'block_elediaaitutor'),
        30,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_elediaaitutor/streamingenabled',
        get_string('setting_streamingenabled', 'block_elediaaitutor'),
        get_string('setting_streamingenabled_desc', 'block_elediaaitutor'),
        0
    ));

    $settings->add(new admin_setting_heading(
        'block_elediaaitutor/headertoken',
        get_string('setting_header_token', 'block_elediaaitutor'),
        get_string('setting_header_token_desc', 'block_elediaaitutor')
    ));

    $settings->add(new admin_setting_configselect(
        'block_elediaaitutor/mcpserviceid',
        get_string('setting_mcpserviceid', 'block_elediaaitutor'),
        get_string('setting_mcpserviceid_desc', 'block_elediaaitutor'),
        0,
        $serviceoptions
    ));

    $settings->add(new admin_setting_configtext(
        'block_elediaaitutor/tokenlifetime',
        get_string('setting_tokenlifetime', 'block_elediaaitutor'),
        get_string('setting_tokenlifetime_desc', 'block_elediaaitutor'),
        3600,
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        'block_elediaaitutor/headerbehaviour',
        get_string('setting_header_behaviour', 'block_elediaaitutor'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'block_elediaaitutor/maxmessagelength',
        get_string('setting_maxmessagelength', 'block_elediaaitutor'),
        get_string('setting_maxmessagelength_desc', 'block_elediaaitutor'),
        4000,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_elediaaitutor/ratelimitperminute',
        get_string('setting_ratelimitperminute', 'block_elediaaitutor'),
        get_string('setting_ratelimitperminute_desc', 'block_elediaaitutor'),
        20,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_elediaaitutor/dailymessagelimit',
        get_string('setting_dailymessagelimit', 'block_elediaaitutor'),
        get_string('setting_dailymessagelimit_desc', 'block_elediaaitutor'),
        0,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_elediaaitutor/enableanalytics',
        get_string('setting_enableanalytics', 'block_elediaaitutor'),
        get_string('setting_enableanalytics_desc', 'block_elediaaitutor'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'block_elediaaitutor/analyticsretentiondays',
        get_string('setting_analyticsretention', 'block_elediaaitutor'),
        get_string('setting_analyticsretention_desc', 'block_elediaaitutor'),
        180,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_elediaaitutor/reclustertoolname',
        get_string('setting_reclustertoolname', 'block_elediaaitutor'),
        get_string('setting_reclustertoolname_desc', 'block_elediaaitutor'),
        '',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configselect(
        'block_elediaaitutor/loggingverbosity',
        get_string('setting_loggingverbosity', 'block_elediaaitutor'),
        get_string('setting_loggingverbosity_desc', 'block_elediaaitutor'),
        1,
        [
            0 => get_string('loglevel_errors', 'block_elediaaitutor'),
            1 => get_string('loglevel_normal', 'block_elediaaitutor'),
            2 => get_string('loglevel_verbose', 'block_elediaaitutor'),
        ]
    ));
}
