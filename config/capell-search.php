<?php

declare(strict_types=1);

return [
    'enabled' => true,
    'show_header_search' => true,
    'driver' => env('CAPELL_SITE_SEARCH_DRIVER', 'database'), // database, site_discovery, or scout
    'route_path' => 'search',
    'page_view' => null,
    'results_per_page' => 10,
    'excerpt_length' => 200,
    'minimum_query_length' => 2,
    'autocomplete' => [
        'enabled' => true,
        'route_path' => 'search/autocomplete',
        'limit' => 6,
        'debounce_ms' => 150,
        'rate_limiter' => 'capell-search-autocomplete',
        'rate_limit' => [
            'per_minute' => 120,
        ],
    ],
    'filters' => [
        'enabled' => true,
    ],
    'click_tracking' => [
        'enabled' => true,
        'route_path' => 'search/click',
        'rate_limiter' => 'capell-search-clicks',
        'match_window_minutes' => 30,
    ],
    'keyboard_shortcuts' => [
        'enabled' => true,
    ],
    'public_urls' => [
        'allowed_schemes' => ['', 'http', 'https'],
        'blocked_path_prefixes' => [
            '/admin',
            '/cp',
            '/filament',
        ],
        'strip_query_keys' => [
            'expires',
            'signature',
            'token',
            'preview',
        ],
        'allowed_meta_keys' => [
            'author',
            'category',
            'date',
            'description',
            'image',
            'image_url',
            'published_at',
            'section',
            'source',
            'source_label',
            'tags',
            'thumbnail',
            'thumbnail_url',
            'updated_at',
        ],
    ],
    'ranking' => [
        'exact_match_boost' => 25.0,
        'recency_days' => 90,
        'click_boost_weight' => 0.25,
        'click_counts_cache_seconds' => 60,
    ],
    'type_labels' => [],
    'record_search_logs' => true,
    'hash_visitor_data' => true,
    'synonyms' => [
        // 'cms' => ['content management system'],
    ],
    'typo_corrections' => [
        // 'capel' => 'capell',
    ],
    'typo_terms' => [
        // 'capell',
    ],
    'typo_max_distance' => 1,
    'promoted_results' => [
        // [
        //     'queries' => ['pricing'],
        //     'title' => 'Pricing',
        //     'url' => '/pricing',
        //     'excerpt' => 'Plan and billing information.',
        //     'type' => 'page',
        //     'score' => 1000.0,
        // ],
    ],
    'source_weights' => [
        // 'page' => 1.0,
    ],
    'searchables' => [
        // 'pages' => [
        //     'label' => 'Pages',
        //     'model' => App\Models\Page::class,
        //     'type' => 'page',
        //     'enabled' => true,
        //     'weight' => 1.0,
        //     'index' => null,
        // ],
    ],
    'database' => [
        'table' => 'pages',
        'columns' => ['title', 'excerpt', 'body'],
        'title_column' => 'title',
        'url_column' => 'slug',
        'excerpt_column' => 'excerpt',
        'body_column' => 'body',
        'type_column' => 'type',
        'site_column' => 'site_id',
        'language_column' => 'language_id',
        'status_column' => 'status',
        'published_status' => 'published',
    ],
    'scout' => [
        'model' => null,
        'url_column' => 'slug',
        'type_column' => 'type',
        'chunk' => 500,
    ],
    'logs' => [
        'table_name' => 'search_logs',
        'retention_days' => 180,
    ],
    'dashboard' => [
        'default_days' => 30,
        'trending_candidate_limit' => 250,
    ],
];
