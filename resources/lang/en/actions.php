<?php

declare(strict_types=1);

return [
    'flushed_sources' => 'Flushed :count search source index: :sources.',
    'indexed_sources' => 'Indexed :count search source: :sources.',
    'invalid_chunk' => 'The --chunk option must be a positive integer.',
    'no_flushable_sources' => 'No flushable Scout search sources were found.',
    'no_indexable_sources' => 'No indexable Scout search sources were found.',
    'purged_logs' => 'Purged :count site search log records.',
    'rehashed_logs' => 'Re-keyed :count site search log records with the current hash secret.',
    'rehash_logs_missing_secret' => 'Search logs have no hash secret, so nothing was re-keyed and search logging is off. Set :key to a long random value, then run this command again.',
    'rehash_logs_undecryptable' => ':count search log records could not be decrypted with the current or previous application keys and were left unchanged. Add the key that encrypted them to APP_PREVIOUS_KEYS and run this command again, or let the retention purge remove them.',
];
