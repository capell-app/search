<?php

declare(strict_types=1);

return [
    'flushed_sources' => 'Suchquellen-Index geleert: :count. Quellen: :sources.',
    'indexed_sources' => 'Suchquellen indexiert: :count. Quellen: :sources.',
    'invalid_chunk' => 'Die Option --chunk muss eine positive ganze Zahl sein.',
    'no_flushable_sources' => 'Es wurden keine leerbaren Scout-Suchquellen gefunden.',
    'no_indexable_sources' => 'Es wurden keine indexierbaren Scout-Suchquellen gefunden.',
    'purged_logs' => 'Bereinigte Protokolleinträge der Website-Suche: :count.',
    'rehashed_logs' => 'Mit dem aktuellen Hash-Geheimnis neu verschlüsselte Protokolleinträge der Website-Suche: :count.',
    'rehash_logs_missing_secret' => 'Für Suchprotokolle ist kein Hash-Geheimnis gesetzt. Es wurde nichts neu verschlüsselt, und die Suchprotokollierung ist aus. Setzen Sie :key auf einen langen Zufallswert und führen Sie diesen Befehl erneut aus.',
    'rehash_logs_undecryptable' => ':count Protokolleinträge der Suche ließen sich weder mit dem aktuellen noch mit früheren Anwendungsschlüsseln entschlüsseln und blieben unverändert. Tragen Sie den Schlüssel, mit dem sie verschlüsselt wurden, in APP_PREVIOUS_KEYS ein und führen Sie diesen Befehl erneut aus, oder lassen Sie sie von der Aufbewahrungsbereinigung entfernen.',
];
