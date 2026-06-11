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
 * @package     block_elediaaitutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    // --- RAG / Tutor MCP server connection. -------------------------------.
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

    // --- Moodle MCP token handling. ---------------------------------------.
    $settings->add(new admin_setting_heading(
        'block_elediaaitutor/headertoken',
        get_string('setting_header_token', 'block_elediaaitutor'),
        get_string('setting_header_token_desc', 'block_elediaaitutor')
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

    // --- Behaviour and limits. --------------------------------------------.
    $settings->add(new admin_setting_heading(
        'block_elediaaitutor/headerbehaviour',
        get_string('setting_header_behaviour', 'block_elediaaitutor'),
        ''
    ));

    $settings->add(new admin_setting_configselect(
        'block_elediaaitutor/defaultdisplaymode',
        get_string('setting_defaultdisplaymode', 'block_elediaaitutor'),
        get_string('setting_defaultdisplaymode_desc', 'block_elediaaitutor'),
        'embedded',
        [
            'embedded' => get_string('displaymode_embedded', 'block_elediaaitutor'),
            'docked' => get_string('displaymode_docked', 'block_elediaaitutor'),
            'modal' => get_string('displaymode_modal', 'block_elediaaitutor'),
            'fullscreen' => get_string('displaymode_fullscreen', 'block_elediaaitutor'),
        ]
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

    // --- Privacy. -----------------------------------------------------------.
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
