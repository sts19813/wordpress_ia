<?php

namespace App\Services;

use App\Models\AiArticle;
use App\Models\AiImage;
use App\Models\Publication;
use App\Models\WordPressSite;
use App\Services\Publications\PublicationEngine;

class PublicationService
{
    public function __construct(
        private readonly PublicationEngine $engine,
    ) {}

    public function createPublication(WordPressSite $site, AiArticle $article, ?AiImage $image = null): Publication
    {
        return $this->engine->createPublication($site, $article, $image);
    }

    public function uploadImage(Publication $publication, string $contents, string $filename, string $mimeType = 'image/jpeg'): Publication
    {
        return $this->engine->uploadImage($publication, $contents, $filename, $mimeType);
    }

    public function createCategory(WordPressSite $site, string $name): array
    {
        return $this->engine->createCategory($site, $name);
    }

    public function createTag(WordPressSite $site, string $name): array
    {
        return $this->engine->createTag($site, $name);
    }

    public function createArticle(Publication $publication, string $status = 'draft'): Publication
    {
        return $this->engine->createArticle($publication, $status);
    }

    public function updateArticle(Publication $publication, array $overrides = []): Publication
    {
        return $this->engine->updateArticle($publication, $overrides);
    }

    public function schedulePublication(Publication $publication, string $scheduledAt): Publication
    {
        return $this->engine->schedulePublication($publication, $scheduledAt);
    }

    public function deletePublication(Publication $publication, bool $force = false): Publication
    {
        return $this->engine->deletePublication($publication, $force);
    }
}
