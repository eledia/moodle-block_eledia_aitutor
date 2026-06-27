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
 * Shell-friendly configuration overview for the eLeDia.ai Tutor block.
 *
 * @package     block_elediaaitutor
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/local/premium.php');
require_once(__DIR__ . '/classes/output/shell.php');

use block_elediaaitutor\local\premium;
use block_elediaaitutor\output\shell;

$context = \core\context\system::instance();
$url = new moodle_url('/blocks/elediaaitutor/configuration.php');

require_login();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('configuration', 'block_elediaaitutor'));
$PAGE->set_heading(shell::is_available() ? '' : get_string('configuration', 'block_elediaaitutor'));
shell::require_css();

$adminsettingsurl = new moodle_url('/admin/settings.php', ['section' => 'blocksettingelediaaitutor']);
$mcpurl = new moodle_url('/webservice/elediamcp/configuration.php');
$literagurl = new moodle_url('/admin/settings.php', ['section' => 'local_literag'], 'settings-connection');
$ragingesturl = new moodle_url('/admin/settings.php', ['section' => 'local_ragingest_settings']);
$ragingestreindexurl = new moodle_url('/local/ragingest/reindex.php');
$managetutorsurl = new moodle_url('/blocks/elediaaitutor/manage_tutors.php');
$previewurl = new moodle_url('/blocks/elediaaitutor/view.php');

$ragserverurl = trim((string) get_config('block_elediaaitutor', 'ragserverurl'));
$mcpserviceid = (int) get_config('block_elediaaitutor', 'mcpserviceid');
$privacyguidelinestext = trim((string) get_config('block_elediaaitutor', 'privacyguidelinestext'));
$enablecoursechat = !empty(get_config('block_elediaaitutor', 'enablecoursechat'));
$premiumactive = premium::has_feature(premium::FEATURE_FOOTER_BRANDING);
$literagavailable = (bool) core_component::get_plugin_directory('local', 'literag');
$mcpavailable = (bool) core_component::get_plugin_directory('webservice', 'elediamcp');
$ragingestavailable = (bool) core_component::get_plugin_directory('local', 'ragingest')
    && class_exists('\\local_ragingest\\course_state')
    && class_exists('\\local_ragingest\\api_client');
$ragingestpending = $ragingestavailable ? \local_ragingest\course_state::pending_ingestion_count() : 0;
$ragingesthealth = [
    'healthy' => false,
    'state' => $ragingestavailable ? 'error' : 'todo',
    'message' => '',
];
if ($ragingestavailable) {
    try {
        $ragingestclient = new \local_ragingest\api_client();
        $ragingestresult = $ragingestclient->healthcheck();
        $ragingesthealth['healthy'] = !empty($ragingestresult['success']);
        $ragingesthealth['state'] = $ragingesthealth['healthy'] ? 'ready' : 'error';
        $ragingesthealth['message'] = $ragingesthealth['healthy']
            ? 'OK'
            : ($ragingestresult['error'] ?: ('HTTP ' . (int) ($ragingestresult['http_code'] ?? 0)));
    } catch (\Throwable $exception) {
        $ragingesthealth['message'] = $exception->getMessage();
    }
}

$checkllmhealth = static function (bool $literagavailable): array {
    $fallback = [
        'healthy' => false,
        'state' => $literagavailable ? 'error' : 'todo',
        'message' => '',
    ];
    if (!$literagavailable) {
        return $fallback;
    }

    $configclass = '\\local_literag\\local\\config';
    $clientclass = '\\local_literag\\local\\llm\\client';
    if (!class_exists($configclass) || !class_exists($clientclass)) {
        $fallback['message'] = 'LiteRAG LLM client not available';
        return $fallback;
    }

    $configfingerprint = sha1(implode('|', [
        $configclass::llm_base_url(),
        $configclass::llm_model(),
        $configclass::llm_api_key() === '' ? '' : sha1($configclass::llm_api_key()),
    ]));
    $cached = json_decode((string) get_config('block_elediaaitutor', 'llmhealthcache'), true);
    if (
        is_array($cached) && !empty($cached['checked']) && ($cached['fingerprint'] ?? '') === $configfingerprint &&
            (time() - (int) $cached['checked']) < 60
    ) {
        return [
            'healthy' => !empty($cached['healthy']),
            'state' => !empty($cached['healthy']) ? 'ready' : 'error',
            'message' => isset($cached['message']) ? (string) $cached['message'] : '',
        ];
    }

    try {
        if ($configclass::llm_api_key() === '') {
            throw new \moodle_exception('configuration_status_llm_missing_key', 'block_elediaaitutor');
        }

        $transport = null;
        if (
            interface_exists('\\local_literag\\local\\http\\transport') &&
                class_exists('\\local_literag\\local\\http\\curl_transport')
        ) {
            $transport = new class ($configclass::llm_allow_private()) implements \local_literag\local\http\transport {
                /** @var bool Whether to allow private HTTP targets. */
                private bool $allowprivate;

                /**
                 * Constructor.
                 *
                 * @param bool $allowprivate Whether to allow private HTTP targets.
                 */
                public function __construct(bool $allowprivate) {
                    $this->allowprivate = $allowprivate;
                }

                /**
                 * POST with a dashboard-friendly timeout.
                 *
                 * @param string $url Absolute URL.
                 * @param string[] $headers Header lines.
                 * @param string $body Request body.
                 * @param int $timeout Requested timeout.
                 * @return array{status: int, body: string, error: string}
                 */
                public function post(string $url, array $headers, string $body, int $timeout): array {
                    $transport = new \local_literag\local\http\curl_transport($this->allowprivate);
                    return $transport->post($url, $headers, $body, min(6, $timeout));
                }
            };
        }

        $client = $transport ? new $clientclass($transport) : new $clientclass();
        $response = $client->chat([
            [
                'role' => 'system',
                'content' => 'You are a health check. Reply with OK only.',
            ],
            [
                'role' => 'user',
                'content' => 'OK?',
            ],
        ], null, 4);
        $healthy = trim($response) !== '';
        $message = $healthy ? 'OK' : 'Empty response';
    } catch (\Throwable $exception) {
        $healthy = false;
        $message = $exception->getMessage();
    }

    set_config('llmhealthcache', json_encode([
        'checked' => time(),
        'fingerprint' => $configfingerprint,
        'healthy' => $healthy,
        'message' => $message,
    ]), 'block_elediaaitutor');

    return [
        'healthy' => $healthy,
        'state' => $healthy ? 'ready' : 'error',
        'message' => $message,
    ];
};

$llmhealth = $checkllmhealth($literagavailable);

$settingurl = static function (string $section, string $anchor): moodle_url {
    return new moodle_url('/admin/settings.php', ['section' => $section], 'admin-' . $anchor);
};
$settingshellurl = static function (string $section, string $hash): moodle_url {
    return new moodle_url('/admin/settings.php', ['section' => $section], $hash);
};
$mcpconfigurl = static function (string $anchor): moodle_url {
    return new moodle_url('/webservice/elediamcp/configuration.php', [], $anchor);
};

$statusrows = [
    [
        'label' => get_string('configuration_status_rag', 'block_elediaaitutor'),
        'value' => $ragserverurl !== '' ? s($ragserverurl) : get_string('configuration_status_missing', 'block_elediaaitutor'),
    ],
    [
        'label' => get_string('configuration_status_mcp', 'block_elediaaitutor'),
        'value' => $mcpserviceid > 0
            ? get_string('configuration_status_configured', 'block_elediaaitutor')
            : get_string('configuration_status_missing', 'block_elediaaitutor'),
    ],
    [
        'label' => get_string('configuration_status_llm', 'block_elediaaitutor'),
        'value' => $llmhealth['healthy']
            ? get_string('configuration_status_llm_ok', 'block_elediaaitutor')
            : get_string('configuration_status_llm_error', 'block_elediaaitutor'),
    ],
    [
        'label' => get_string('configuration_status_ragingest', 'block_elediaaitutor'),
        'value' => !$ragingestavailable
            ? get_string('configuration_status_missing', 'block_elediaaitutor')
            : ($ragingestpending > 0
                ? get_string('configuration_status_ragingest_pending', 'block_elediaaitutor', $ragingestpending)
                : ($ragingesthealth['healthy']
                    ? get_string('configuration_status_llm_ok', 'block_elediaaitutor')
                    : get_string('configuration_status_llm_error', 'block_elediaaitutor'))),
    ],
    [
        'label' => get_string('configuration_status_globalchat', 'block_elediaaitutor'),
        'value' => !empty(get_config('block_elediaaitutor', 'enableglobalchat')) ? get_string('yes') : get_string('no'),
    ],
    [
        'label' => get_string('configuration_status_coursechat', 'block_elediaaitutor'),
        'value' => !empty(get_config('block_elediaaitutor', 'enablecoursechat')) ? get_string('yes') : get_string('no'),
    ],
    [
        'label' => get_string('configuration_status_premium', 'block_elediaaitutor'),
        'value' => $premiumactive
            ? get_string('configuration_status_premium_active', 'block_elediaaitutor')
            : get_string('configuration_status_premium_inactive', 'block_elediaaitutor'),
    ],
];

$card = static function (
    string $icon,
    string $title,
    string $description,
    moodle_url $link,
    string $label,
    string $actionicon
): string {
    return html_writer::tag(
        'section',
        html_writer::tag(
            'div',
            html_writer::span(
                html_writer::tag('i', '', ['class' => 'fa fa-' . $icon, 'aria-hidden' => 'true']),
                'lh-plugin-card__icon lh-plugin-card__icon--generic'
            ) .
            html_writer::tag(
                'div',
                html_writer::tag('div', $title, ['class' => 'lh-plugin-card__title']),
                ['class' => 'lh-plugin-card__meta']
            ) .
            html_writer::tag(
                'div',
                html_writer::link(
                    $link,
                    html_writer::tag('i', '', ['class' => 'fa fa-' . $actionicon, 'aria-hidden' => 'true']) .
                    html_writer::span($label, 'sr-only'),
                    [
                        'class' => 'lh-icon-action',
                        'aria-label' => $label,
                        'title' => $label,
                    ]
                ),
                ['class' => 'lh-plugin-card__actions']
            ),
            ['class' => 'lh-plugin-card__top']
        ) .
        html_writer::tag('p', $description, ['class' => 'lh-plugin-card__body']),
        ['class' => 'lh-plugin-card']
    );
};

$wizardstep = static function (
    int $number,
    string $icon,
    string $title,
    string $body,
    moodle_url $url,
    string $linklabel,
    bool $ready,
    array $tasks = []
): string {
    if ($tasks) {
        $ready = array_reduce($tasks, static fn(bool $carry, array $task): bool => $carry && !empty($task['ready']), true);
    }
    $statuslabel = $ready
        ? get_string('configuration_wizard_status_ready', 'block_elediaaitutor')
        : get_string('configuration_wizard_status_todo', 'block_elediaaitutor');
    $statusclass = $ready ? 'eat-setup-step__status--ready' : 'eat-setup-step__status--error';
    $taskhtml = '';
    if ($tasks) {
        $taskhtml .= html_writer::start_tag('ol', ['class' => 'eat-setup-task-list']);
        foreach ($tasks as $task) {
            $taskready = !empty($task['ready']);
            $taskstate = $task['state'] ?? ($taskready ? 'ready' : 'error');
            $taskstatusclass = 'eat-setup-task__state--' . clean_param((string) $taskstate, PARAM_ALPHANUMEXT);
            $taskstatuslabel = $taskstate === 'error'
                ? get_string('configuration_wizard_status_error', 'block_elediaaitutor')
                : ($taskready
                    ? get_string('configuration_wizard_status_ready', 'block_elediaaitutor')
                    : get_string('configuration_wizard_status_todo', 'block_elediaaitutor'));
            $taskhtml .= html_writer::tag(
                'li',
                html_writer::span('', 'eat-setup-task__dot ' . $taskstatusclass) .
                html_writer::span(
                    html_writer::span($task['title'], 'eat-setup-task__title') .
                    html_writer::span($task['body'], 'eat-setup-task__body') .
                    html_writer::span($taskstatuslabel, 'sr-only'),
                    'eat-setup-task__text'
                ) .
                html_writer::link(
                    $task['url'],
                    html_writer::tag('i', '', ['class' => 'fa fa-arrow-right', 'aria-hidden' => 'true']) .
                    html_writer::span(get_string('configuration_wizard_task_open', 'block_elediaaitutor'), 'sr-only'),
                    [
                        'class' => 'lh-icon-action eat-setup-task__action',
                        'aria-label' => $task['title'],
                        'title' => $task['title'],
                    ]
                ),
                ['class' => 'eat-setup-task']
            );
        }
        $taskhtml .= html_writer::end_tag('ol');
    }

    return html_writer::tag(
        'section',
        html_writer::div(
            html_writer::span(
                html_writer::tag('i', '', ['class' => 'fa fa-' . $icon, 'aria-hidden' => 'true']),
                'eat-setup-step__icon'
            ) .
            html_writer::span($statuslabel, 'eat-setup-step__status ' . $statusclass),
            'eat-setup-step__top'
        ) .
        html_writer::tag('h3', $title, ['class' => 'eat-setup-step__title']) .
        html_writer::tag('p', $body, ['class' => 'eat-setup-step__body']) .
        $taskhtml,
        ['class' => 'eat-setup-step']
    );
};

echo $OUTPUT->header();

shell::open(shell::ACTIVE_CONFIGURATION);

if (!shell::is_available()) {
    echo $OUTPUT->heading(get_string('configuration', 'block_elediaaitutor'), 2);
    echo html_writer::tag('p', get_string('configuration_subtitle', 'block_elediaaitutor'));
}

echo html_writer::start_div('path-block-elediaaitutor');

$ragingestcta = '';
if ($ragingestpending > 0) {
    $queueurl = new moodle_url('/local/ragingest/reindex.php', [
        'queuepending' => 1,
        'sesskey' => sesskey(),
    ]);
    $ragingestcta = html_writer::tag(
        'div',
        html_writer::tag('strong', get_string('configuration_ragingest_pending_title', 'block_elediaaitutor')) .
        html_writer::tag(
            'p',
            get_string('configuration_ragingest_pending_body', 'block_elediaaitutor', $ragingestpending)
        ) .
        $OUTPUT->single_button(
            $queueurl,
            get_string('configuration_ragingest_index_now', 'block_elediaaitutor'),
            'post'
        ),
        ['class' => 'eat-setup-callout eat-setup-callout--warning']
    );
}

echo html_writer::tag(
    'section',
    html_writer::tag(
        'div',
        html_writer::tag(
            'h2',
            get_string('configuration_wizard_title', 'block_elediaaitutor'),
            ['class' => 'eat-setup-wizard__title']
        ) .
        html_writer::tag(
            'p',
            get_string('configuration_wizard_desc', 'block_elediaaitutor'),
            ['class' => 'eat-setup-wizard__intro']
        ) .
        $ragingestcta,
        ['class' => 'eat-setup-wizard__head']
    ) .
    html_writer::div(
        $wizardstep(
            1,
            'database',
            get_string('configuration_wizard_literag_title', 'block_elediaaitutor'),
            get_string('configuration_wizard_literag_desc', 'block_elediaaitutor'),
            $literagurl,
            get_string('configuration_wizard_literag_link', 'block_elediaaitutor'),
            $literagavailable,
            [
                [
                    'title' => get_string('configuration_wizard_task_literag_llm_title', 'block_elediaaitutor'),
                    'body' => get_string('configuration_wizard_task_literag_llm_body', 'block_elediaaitutor'),
                    'url' => $settingshellurl('local_literag', 'settings-llm'),
                    'ready' => $llmhealth['healthy'],
                    'state' => $llmhealth['state'],
                ],
                [
                    'title' => get_string('configuration_wizard_task_literag_retrieval_title', 'block_elediaaitutor'),
                    'body' => get_string('configuration_wizard_task_literag_retrieval_body', 'block_elediaaitutor'),
                    'url' => $settingshellurl('local_literag', 'settings-retrieval'),
                    'ready' => $literagavailable,
                ],
                [
                    'title' => get_string('configuration_wizard_task_literag_tools_title', 'block_elediaaitutor'),
                    'body' => get_string('configuration_wizard_task_literag_tools_body', 'block_elediaaitutor'),
                    'url' => $settingshellurl('local_literag', 'settings-livetools'),
                    'ready' => $literagavailable,
                ],
            ]
        ) .
        $wizardstep(
            2,
            'upload',
            get_string('configuration_wizard_ragingest_title', 'block_elediaaitutor'),
            get_string('configuration_wizard_ragingest_desc', 'block_elediaaitutor'),
            $ragingesturl,
            get_string('configuration_wizard_ragingest_link', 'block_elediaaitutor'),
            $ragingestavailable && $ragingesthealth['healthy'] && $ragingestpending === 0,
            [
                [
                    'title' => get_string('configuration_wizard_task_ragingest_endpoint_title', 'block_elediaaitutor'),
                    'body' => get_string('configuration_wizard_task_ragingest_endpoint_body', 'block_elediaaitutor'),
                    'url' => $ragingesturl,
                    'ready' => $ragingesthealth['healthy'],
                    'state' => $ragingesthealth['state'],
                ],
                [
                    'title' => get_string('configuration_wizard_task_ragingest_index_title', 'block_elediaaitutor'),
                    'body' => $ragingestpending > 0
                        ? get_string('configuration_status_ragingest_pending', 'block_elediaaitutor', $ragingestpending)
                        : get_string('configuration_wizard_task_ragingest_index_body', 'block_elediaaitutor'),
                    'url' => $ragingestreindexurl,
                    'ready' => $ragingestavailable && $ragingestpending === 0,
                    'state' => !$ragingestavailable ? 'todo' : ($ragingestpending > 0 ? 'error' : 'ready'),
                ],
            ]
        ) .
        $wizardstep(
            3,
            'plug',
            get_string('configuration_wizard_mcp_title', 'block_elediaaitutor'),
            get_string('configuration_wizard_mcp_desc', 'block_elediaaitutor'),
            $mcpurl,
            get_string('configuration_wizard_mcp_link', 'block_elediaaitutor'),
            $mcpavailable,
            [
                [
                    'title' => get_string('configuration_wizard_task_mcp_services_title', 'block_elediaaitutor'),
                    'body' => get_string('configuration_wizard_task_mcp_services_body', 'block_elediaaitutor'),
                    'url' => $mcpconfigurl('id_serviceshdr'),
                    'ready' => $mcpavailable,
                ],
                [
                    'title' => get_string('configuration_wizard_task_mcp_limits_title', 'block_elediaaitutor'),
                    'body' => get_string('configuration_wizard_task_mcp_limits_body', 'block_elediaaitutor'),
                    'url' => $mcpconfigurl('id_rate_limit_per_minute'),
                    'ready' => $mcpavailable,
                ],
                [
                    'title' => get_string('configuration_wizard_task_mcp_security_title', 'block_elediaaitutor'),
                    'body' => get_string('configuration_wizard_task_mcp_security_body', 'block_elediaaitutor'),
                    'url' => $mcpconfigurl('id_allowed_origins'),
                    'ready' => $mcpavailable,
                ],
            ]
        ) .
        $wizardstep(
            4,
            'sliders',
            get_string('configuration_wizard_operator_title', 'block_elediaaitutor'),
            get_string('configuration_wizard_operator_desc', 'block_elediaaitutor'),
            $adminsettingsurl,
            get_string('configuration_admin_settings_link', 'block_elediaaitutor'),
            $ragserverurl !== '' && $mcpserviceid > 0,
            [
                [
                    'title' => get_string('configuration_wizard_task_operator_rag_title', 'block_elediaaitutor'),
                    'body' => get_string('configuration_wizard_task_operator_rag_body', 'block_elediaaitutor'),
                    'url' => $settingurl('blocksettingelediaaitutor', 'ragserverurl'),
                    'ready' => $ragserverurl !== '',
                ],
                [
                    'title' => get_string('configuration_wizard_task_operator_mcp_title', 'block_elediaaitutor'),
                    'body' => get_string('configuration_wizard_task_operator_mcp_body', 'block_elediaaitutor'),
                    'url' => $settingurl('blocksettingelediaaitutor', 'mcpserviceid'),
                    'ready' => $mcpserviceid > 0,
                ],
                [
                    'title' => get_string('configuration_wizard_task_operator_privacy_title', 'block_elediaaitutor'),
                    'body' => get_string('configuration_wizard_task_operator_privacy_body', 'block_elediaaitutor'),
                    'url' => $settingurl('blocksettingelediaaitutor', 'privacyguidelinestext'),
                    'ready' => $privacyguidelinestext !== '',
                ],
            ]
        ) .
        $wizardstep(
            5,
            'comments',
            get_string('configuration_wizard_tutors_title', 'block_elediaaitutor'),
            get_string('configuration_wizard_tutors_desc', 'block_elediaaitutor'),
            $managetutorsurl,
            get_string('configuration_tutors_link', 'block_elediaaitutor'),
            true,
            [
                [
                    'title' => get_string('configuration_wizard_task_tutors_design_title', 'block_elediaaitutor'),
                    'body' => get_string('configuration_wizard_task_tutors_design_body', 'block_elediaaitutor'),
                    'url' => $settingurl('blocksettingelediaaitutor', 'brandaccent'),
                    'ready' => true,
                ],
                [
                    'title' => get_string('configuration_wizard_task_tutors_persona_title', 'block_elediaaitutor'),
                    'body' => get_string('configuration_wizard_task_tutors_persona_body', 'block_elediaaitutor'),
                    'url' => $settingurl('blocksettingelediaaitutor', 'persona'),
                    'ready' => true,
                ],
                [
                    'title' => get_string('configuration_wizard_task_tutors_library_title', 'block_elediaaitutor'),
                    'body' => get_string('configuration_wizard_task_tutors_library_body', 'block_elediaaitutor'),
                    'url' => $managetutorsurl,
                    'ready' => true,
                ],
            ]
        ) .
        $wizardstep(
            6,
            'eye',
            get_string('configuration_wizard_preview_title', 'block_elediaaitutor'),
            get_string('configuration_wizard_preview_desc', 'block_elediaaitutor'),
            $previewurl,
            get_string('configuration_preview_link', 'block_elediaaitutor'),
            $ragserverurl !== '' && $mcpserviceid > 0,
            [
                [
                    'title' => get_string('configuration_wizard_task_preview_open_title', 'block_elediaaitutor'),
                    'body' => get_string('configuration_wizard_task_preview_open_body', 'block_elediaaitutor'),
                    'url' => $previewurl,
                    'ready' => $ragserverurl !== '' && $mcpserviceid > 0 && $llmhealth['healthy'],
                ],
                [
                    'title' => get_string('configuration_wizard_task_preview_course_title', 'block_elediaaitutor'),
                    'body' => get_string('configuration_wizard_task_preview_course_body', 'block_elediaaitutor'),
                    'url' => $settingurl('blocksettingelediaaitutor', 'enablecoursechat'),
                    'ready' => $enablecoursechat,
                ],
            ]
        ),
        'eat-setup-wizard__steps'
    ),
    ['class' => 'eat-setup-wizard']
);

echo html_writer::tag(
    'section',
    html_writer::tag(
        'div',
        html_writer::span(
            html_writer::tag('i', '', ['class' => 'fa fa-check-circle', 'aria-hidden' => 'true']),
            'lh-plugin-card__icon lh-plugin-card__icon--generic'
        ) .
        html_writer::tag(
            'div',
            html_writer::tag(
                'div',
                get_string('configuration_status_title', 'block_elediaaitutor'),
                ['class' => 'lh-plugin-card__title']
            ),
            ['class' => 'lh-plugin-card__meta']
        ),
        ['class' => 'lh-plugin-card__top']
    ) .
    html_writer::start_tag('dl', ['class' => 'lh-plugin-card__body']) .
    implode('', array_map(static function (array $row): string {
        return html_writer::tag('dt', $row['label']) . html_writer::tag('dd', $row['value']);
    }, $statusrows)) .
    html_writer::end_tag('dl'),
    ['class' => 'lh-plugin-card']
);

echo html_writer::end_div();

shell::close();

echo $OUTPUT->footer();
