<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('level', 20)->after('user_id')->index();
            $table->string('event', 50)->after('level')->index();
            $table->string('source', 100)->nullable()->after('event');
            $table->text('message')->after('source');
            $table->json('context')->nullable()->after('message');
            $table->nullableMorphs('subject');
            $table->timestamp('occurred_at')->after('subject_id')->index();
        });

        $this->backfillPublications();
        $this->backfillSchedulers();
        $this->backfillArticles();
        $this->backfillImages();
        $this->backfillConnections();
        $this->backfillSources();
    }

    public function down(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['level']);
            $table->dropIndex(['event']);
            $table->dropIndex(['occurred_at']);
            $table->dropMorphs('subject');
            $table->dropColumn([
                'user_id',
                'level',
                'event',
                'source',
                'message',
                'context',
                'occurred_at',
            ]);
        });
    }

    private function backfillPublications(): void
    {
        DB::table('publications')
            ->leftJoin('ai_articles', 'ai_articles.id', '=', 'publications.ai_article_id')
            ->leftJoin('wordpress_sites', 'wordpress_sites.id', '=', 'publications.wordpress_site_id')
            ->where('publications.status', 'failed')
            ->select([
                'publications.*',
                'ai_articles.title as article_title',
                'wordpress_sites.name as profile_name',
            ])
            ->orderBy('publications.id')
            ->chunkById(100, function ($publications): void {
                foreach ($publications as $publication) {
                    $this->insert([
                        'user_id' => $publication->user_id,
                        'level' => 'error',
                        'event' => 'publication_failed',
                        'source' => 'Publicaciones',
                        'message' => $publication->error_message ?: 'El destino rechazó la publicación.',
                        'context' => [
                            'article' => $publication->article_title,
                            'profile' => $publication->profile_name,
                        ],
                        'subject_type' => 'App\\Models\\Publication',
                        'subject_id' => $publication->id,
                        'occurred_at' => $publication->updated_at,
                    ]);
                }
            }, 'publications.id', 'id');

        DB::table('publications')
            ->leftJoin('ai_articles', 'ai_articles.id', '=', 'publications.ai_article_id')
            ->leftJoin('wordpress_sites', 'wordpress_sites.id', '=', 'publications.wordpress_site_id')
            ->where('publications.status', 'published')
            ->whereNotNull('publications.published_at')
            ->where(function ($query): void {
                $query->whereNotNull('publications.remote_url')
                    ->orWhereNotNull('publications.remote_post_id')
                    ->orWhereNotNull('publications.remote_post_key');
            })
            ->select([
                'publications.*',
                'ai_articles.title as article_title',
                'wordpress_sites.name as profile_name',
            ])
            ->orderBy('publications.id')
            ->chunkById(100, function ($publications): void {
                foreach ($publications as $publication) {
                    $article = $publication->article_title ?: 'Artículo sin título';
                    $profile = $publication->profile_name ?: 'perfil externo';
                    $this->insert([
                        'user_id' => $publication->user_id,
                        'level' => 'success',
                        'event' => 'publication_published',
                        'source' => 'Publicaciones',
                        'message' => "“{$article}” se publicó en {$profile}.",
                        'context' => [
                            'article' => $article,
                            'profile' => $profile,
                            'remote_url' => $publication->remote_url,
                        ],
                        'subject_type' => 'App\\Models\\Publication',
                        'subject_id' => $publication->id,
                        'occurred_at' => $publication->published_at,
                    ]);
                }
            }, 'publications.id', 'id');
    }

    private function backfillSchedulers(): void
    {
        DB::table('schedulers')
            ->where('status', 'failed')
            ->orderBy('id')
            ->chunkById(100, function ($tasks): void {
                foreach ($tasks as $task) {
                    $this->insert([
                        'user_id' => $task->user_id,
                        'level' => 'error',
                        'event' => 'task_failed',
                        'source' => 'Programador',
                        'message' => $task->last_error ?: 'El proceso terminó con error.',
                        'context' => ['task' => $task->name, 'type' => $task->type],
                        'subject_type' => 'App\\Models\\Scheduler',
                        'subject_id' => $task->id,
                        'occurred_at' => $task->finished_at ?: $task->updated_at,
                    ]);
                }
            });
    }

    private function backfillArticles(): void
    {
        DB::table('ai_articles')
            ->where('status', 'failed')
            ->orderBy('id')
            ->chunkById(100, function ($articles): void {
                foreach ($articles as $article) {
                    $this->insert([
                        'user_id' => $article->user_id,
                        'level' => 'error',
                        'event' => 'article_failed',
                        'source' => 'Artículos IA',
                        'message' => $article->generation_error ?: 'No fue posible generar el artículo.',
                        'context' => ['article' => $article->title],
                        'subject_type' => 'App\\Models\\AiArticle',
                        'subject_id' => $article->id,
                        'occurred_at' => $article->updated_at,
                    ]);
                }
            });
    }

    private function backfillImages(): void
    {
        DB::table('ai_images')
            ->leftJoin('ai_articles', 'ai_articles.id', '=', 'ai_images.ai_article_id')
            ->where('ai_images.status', 'failed')
            ->select([
                'ai_images.*',
                'ai_articles.user_id as article_user_id',
                'ai_articles.title as article_title',
            ])
            ->orderBy('ai_images.id')
            ->chunkById(100, function ($images): void {
                foreach ($images as $image) {
                    $this->insert([
                        'user_id' => $image->article_user_id,
                        'level' => 'error',
                        'event' => 'image_failed',
                        'source' => 'Imágenes IA',
                        'message' => $image->generation_error ?: 'No fue posible generar la imagen.',
                        'context' => ['article' => $image->article_title],
                        'subject_type' => 'App\\Models\\AiImage',
                        'subject_id' => $image->id,
                        'occurred_at' => $image->updated_at,
                    ]);
                }
            }, 'ai_images.id', 'id');
    }

    private function backfillConnections(): void
    {
        DB::table('wordpress_sites')
            ->where('status', 'error')
            ->orderBy('id')
            ->chunkById(100, function ($profiles): void {
                foreach ($profiles as $profile) {
                    $this->insert([
                        'user_id' => $profile->user_id,
                        'level' => 'error',
                        'event' => 'connection_failed',
                        'source' => 'Perfiles',
                        'message' => $profile->connection_error ?: 'Falló la conexión con el perfil de publicación.',
                        'context' => ['profile' => $profile->name],
                        'subject_type' => 'App\\Models\\WordPressSite',
                        'subject_id' => $profile->id,
                        'occurred_at' => $profile->last_tested_at ?: $profile->updated_at,
                    ]);
                }
            });
    }

    private function backfillSources(): void
    {
        DB::table('source_sites')
            ->where('status', 'error')
            ->orderBy('id')
            ->chunkById(100, function ($sources): void {
                foreach ($sources as $source) {
                    $this->insert([
                        'user_id' => $source->automation_user_id,
                        'level' => 'error',
                        'event' => 'source_failed',
                        'source' => 'Sitios fuente',
                        'message' => "La fuente {$source->name} se encuentra con error.",
                        'context' => ['source' => $source->name],
                        'subject_type' => 'App\\Models\\SourceSite',
                        'subject_id' => $source->id,
                        'occurred_at' => $source->last_synced_at ?: $source->updated_at,
                    ]);
                }
            });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insert(array $attributes): void
    {
        DB::table('system_logs')->insert([
            ...$attributes,
            'context' => isset($attributes['context']) ? json_encode($attributes['context']) : null,
            'created_at' => $attributes['occurred_at'] ?: now(),
            'updated_at' => $attributes['occurred_at'] ?: now(),
        ]);
    }
};
