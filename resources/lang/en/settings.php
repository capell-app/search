<?php

declare(strict_types=1);

return [
    'driver' => 'Search driver',
    'driver_options' => [
        'database' => 'Database',
        'site_discovery' => 'Site Discovery URL registry',
        'scout' => 'Scout',
    ],
    'enabled' => 'Enable site search',
    'enabled_helper' => 'Allow visitors to use the public site search route.',
    'hash_visitor_data' => 'Hash visitor data',
    'hash_visitor_data_helper' => 'Hash visitor identifiers before storing search logs.',
    'log_retention_days' => 'Log retention',
    'minimum_query_length' => 'Minimum query length',
    'record_search_logs' => 'Record search logs',
    'record_search_logs_helper' => 'Store search terms and result counts for admin insights.',
    'results_per_page' => 'Results per page',
    'show_header_search' => 'Show search in the header',
    'show_header_search_helper' => 'Render the header search control when the active theme exposes a compatible header slot.',
    'title' => 'Search settings',
];
