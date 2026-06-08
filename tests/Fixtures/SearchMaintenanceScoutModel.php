<?php

declare(strict_types=1);

namespace Capell\Search\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class SearchMaintenanceScoutModel extends Model
{
    use HasFactory;

    public static ?int $indexedChunk = null;

    public static int $indexCalls = 0;

    public static int $flushCalls = 0;

    public static function resetMaintenanceState(): void
    {
        self::$indexedChunk = null;
        self::$indexCalls = 0;
        self::$flushCalls = 0;
    }

    public static function makeAllSearchable(?int $chunk = null): void
    {
        self::$indexedChunk = $chunk;
        self::$indexCalls++;
    }

    public static function removeAllFromSearch(): void
    {
        self::$flushCalls++;
    }
}
