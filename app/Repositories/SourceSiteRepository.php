<?php

namespace App\Repositories;

use App\Models\SourceSite;
use Illuminate\Database\Eloquent\Collection;

class SourceSiteRepository
{
    public function getForAdmin(): Collection
    {
        return SourceSite::query()
            ->latest()
            ->get();
    }
}
