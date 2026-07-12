<?php

declare(strict_types=1);

namespace Capell\Search\Tests\Fixtures;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
final class SearchAdditionalCoverageScoutRecord implements Arrayable
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(private readonly array $attributes) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
