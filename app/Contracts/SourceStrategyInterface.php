<?php

namespace App\Contracts;

use App\Models\SourceSite;
use Illuminate\Support\Collection;

interface SourceStrategyInterface
{
    public function validate(SourceSite $sourceSite): void;

    public function fetch(SourceSite $sourceSite): mixed;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function parse(mixed $payload, SourceSite $sourceSite): Collection;
}
