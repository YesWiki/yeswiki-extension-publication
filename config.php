<?php

/* Settings of the headless browser that prints PDFs; each can be overridden in yeswiki.config.php, README.md describes them. */

return [
    'htmltopdf_path' => '/usr/bin/chromium',
    'htmltopdf_service_url' => '',
    'page_load_timeout' => 60000,
    'htmltopdf_options' => [
        'windowSize' => [
            1920,
            1080,
        ],
        'noSandbox' => true,
        'headless' => true,
        'userAgent' => 'YesWiki/5',
        'startupTimeout' => 30,
        'sendSyncDefaultTimeout' => 10000,
        'customFlags' => [
            '--crash-dumps-dir=/tmp',
            '--remote-allow-origins=*',
        ],
    ],
    'htmltopdf_service_authorized_domains' => [],
    'htmltopdf_base_url' => null,
    'publication_editable_config_params' => [
        'htmltopdf_path',
        [
            'htmltopdf_options' => [
                'windowSize',
                'userAgent',
            ],
        ],
        'htmltopdf_service_authorized_domains',
        'htmltopdf_service_url',
    ],
];
