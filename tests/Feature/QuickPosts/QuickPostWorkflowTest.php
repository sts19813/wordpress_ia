<?php

namespace Tests\Feature\QuickPosts;

use App\Jobs\CaptureQuickPost;
use App\Jobs\GenerateAiArticle;
use App\Jobs\GenerateAiImage;
use App\Models\AiArticle;
use App\Models\AiImage;
use App\Models\AiPromptProfile;
use App\Models\Scheduler;
use App\Models\SourcePost;
use App\Models\SourcePostMedia;
use App\Models\User;
use App\Services\AiArticleService;
use App\Services\AiPromptProfileService;
use App\Services\QuickPosts\OriginalPostImageService;
use App\Services\QuickPosts\SocialPostCaptureService;
use App\Services\SchedulerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class QuickPostWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_social_url_returns_immediately_and_starts_the_capture_queue(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $url = 'https://www.facebook.com/share/p/1Zvt11XRZJ/';
        app(AiPromptProfileService::class)->ensureDefaultFor($user);
        $selectedProfile = $user->aiPromptProfiles()->create([
            'name' => 'Social muy breve',
            'system_prompt' => AiPromptProfile::DEFAULT_SYSTEM_PROMPT,
            'content_length' => 'very_short',
        ]);

        $response = $this->actingAs($user)->post(route('admin.quick-posts.store'), [
            'url' => $url,
            'ai_prompt_profile_id' => $selectedProfile->id,
            'image_mode' => 'original',
        ]);

        $task = Scheduler::query()->sole();
        $response->assertRedirect(route('admin.scheduler.index', ['task' => $task->id]));
        $this->assertSame($url, $task->payload['url']);
        $this->assertSame([], $task->payload['source_post_ids']);
        $this->assertSame('original', $task->payload['image_mode']);
        $this->assertSame($selectedProfile->id, $task->payload['profile_id']);
        $this->assertFalse($task->payload['generate_image']);
        Queue::assertPushedOn('social-capture', CaptureQuickPost::class);
    }

    public function test_quick_post_form_asks_how_images_should_be_handled(): void
    {
        $user = User::factory()->create();
        app(AiPromptProfileService::class)->ensureDefaultFor($user);
        $user->aiPromptProfiles()->create([
            'name' => 'Perfil para redes',
            'system_prompt' => AiPromptProfile::DEFAULT_SYSTEM_PROMPT,
            'content_length' => 'very_short',
        ]);

        $this->actingAs($user)
            ->get(route('admin.quick-posts.create'))
            ->assertOk()
            ->assertSee('Generar imágenes nuevas con IA')
            ->assertSee('Conservar las imágenes originales')
            ->assertSee('name="image_mode"', false)
            ->assertSee('name="ai_prompt_profile_id"', false)
            ->assertSee('Perfil para redes')
            ->assertSee('Muy corto (150–200 palabras)');
    }

    public function test_quick_post_cannot_use_another_users_profile(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherProfile = app(AiPromptProfileService::class)->ensureDefaultFor($otherUser);

        $this->actingAs($user)
            ->post(route('admin.quick-posts.store'), [
                'url' => 'https://x.com/openai/status/123456',
                'ai_prompt_profile_id' => $otherProfile->id,
                'image_mode' => 'original',
            ])
            ->assertSessionHasErrors('ai_prompt_profile_id');

        $this->assertDatabaseCount('schedulers', 0);
    }

    public function test_capture_job_archives_the_original_then_hands_off_to_the_ai_queue(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $url = 'https://x.com/openai/status/123456';
        $profile = app(AiPromptProfileService::class)->ensureDefaultFor($user);
        $task = app(SchedulerService::class)->createQuickPostTask($user, $profile, $url);
        $sourcePost = SourcePost::query()->create([
            'origin_type' => SourcePost::ORIGIN_QUICK_POST,
            'social_platform' => 'x',
            'title' => 'Publicación original archivada',
            'content' => 'Contenido original.',
            'url' => $url,
            'canonical_url' => $url,
            'hash' => hash('sha256', 'quick-post-test'),
            'status' => SourcePost::STATUS_FETCHED,
            'captured_at' => now(),
        ]);
        $capture = Mockery::mock(SocialPostCaptureService::class);
        $capture->shouldReceive('capture')->once()->with($url)->andReturn($sourcePost->load('media'));

        (new CaptureQuickPost($task->id))->handle($capture, app(SchedulerService::class));

        $task->refresh();
        $this->assertSame($sourcePost->id, $task->source_post_id);
        $this->assertSame([$sourcePost->id], $task->payload['source_post_ids']);
        $this->assertSame(Scheduler::STATUS_QUEUED, $task->status);
        Queue::assertPushedOn('ai-text', GenerateAiArticle::class);
    }

    public function test_quick_post_archive_and_original_media_are_visible(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $post = SourcePost::query()->create([
            'origin_type' => SourcePost::ORIGIN_QUICK_POST,
            'social_platform' => 'instagram',
            'title' => 'Post visual archivado',
            'content' => 'Texto de Instagram.',
            'url' => 'https://www.instagram.com/p/ABC123/',
            'canonical_url' => 'https://www.instagram.com/p/ABC123/',
            'hash' => hash('sha256', 'quick-post-media'),
            'status' => SourcePost::STATUS_FETCHED,
            'captured_at' => now(),
        ]);
        Storage::disk('local')->put('source-posts/test/original.jpg', 'image');
        $media = SourcePostMedia::query()->create([
            'source_post_id' => $post->id,
            'position' => 0,
            'original_url' => 'https://cdninstagram.com/original.jpg',
            'url_hash' => hash('sha256', 'original-image'),
            'file_path' => 'source-posts/test/original.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $this->actingAs($user)
            ->get(route('admin.quick-posts.index'))
            ->assertOk()
            ->assertSee('Post visual archivado')
            ->assertSee('1 archivadas');

        $this->actingAs($user)
            ->get(route('admin.news.show', $post))
            ->assertOk()
            ->assertSee('Texto de Instagram.')
            ->assertSee('Post rápido');

        $this->actingAs($user)
            ->get(route('admin.source-post-media.file', $media))
            ->assertOk();
    }

    public function test_invalid_or_unsupported_urls_never_start_capture(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.quick-posts.store'), ['url' => 'https://example.com/article'])
            ->assertSessionHasErrors('url');

        $this->assertDatabaseCount('schedulers', 0);
    }

    public function test_original_media_is_copied_to_the_generated_draft_for_later_publication(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $post = SourcePost::query()->create([
            'origin_type' => SourcePost::ORIGIN_QUICK_POST,
            'social_platform' => 'facebook',
            'title' => 'Post original',
            'content' => 'Contenido original.',
            'url' => 'https://www.facebook.com/story.php?story_fbid=1&id=2',
            'canonical_url' => 'https://www.facebook.com/story.php?story_fbid=1&id=2',
            'hash' => hash('sha256', 'quick-post-original-images'),
            'status' => SourcePost::STATUS_FETCHED,
            'captured_at' => now(),
        ]);
        $article = $user->aiArticles()->create([
            'source_post_ids' => [$post->id],
            'title' => 'Post recreado',
            'content' => '<p>Contenido recreado.</p>',
            'status' => AiArticle::STATUS_DRAFT,
        ]);

        foreach ([0, 1] as $position) {
            $sourcePath = "source-posts/test/original-{$position}.jpg";
            Storage::disk('local')->put($sourcePath, "image-{$position}");
            SourcePostMedia::query()->create([
                'source_post_id' => $post->id,
                'position' => $position,
                'original_url' => "https://facebook.test/original-{$position}.jpg",
                'url_hash' => hash('sha256', "original-image-{$position}"),
                'file_path' => $sourcePath,
                'mime_type' => 'image/jpeg',
                'width' => 1200,
                'height' => 630,
            ]);
        }

        $count = app(OriginalPostImageService::class)->attach($article, $post->fresh('media'));
        $images = $article->fresh('images')->images;

        $this->assertSame(2, $count);
        $this->assertSame(AiImage::TYPE_MAIN, $images->first()->type);
        $this->assertSame(AiImage::TYPE_VARIANT, $images->last()->type);
        $this->assertTrue($images->every(fn (AiImage $image) => $image->model === 'original'));
        $this->assertTrue($images->every(fn (AiImage $image) => Storage::disk('local')->exists($image->file_path)));
    }

    public function test_original_mode_finishes_the_ai_draft_without_generating_a_new_image(): void
    {
        Queue::fake();
        Storage::fake('local');
        $user = User::factory()->create();
        $profile = app(AiPromptProfileService::class)->ensureDefaultFor($user);
        $url = 'https://www.instagram.com/p/ORIGINAL123/';
        $task = app(SchedulerService::class)->createQuickPostTask($user, $profile, $url, 'original');
        $post = SourcePost::query()->create([
            'origin_type' => SourcePost::ORIGIN_QUICK_POST,
            'social_platform' => 'instagram',
            'title' => 'Post original',
            'content' => 'Contenido original.',
            'url' => $url,
            'canonical_url' => $url,
            'hash' => hash('sha256', 'quick-post-original-mode'),
            'status' => SourcePost::STATUS_FETCHED,
            'captured_at' => now(),
        ]);
        Storage::disk('local')->put('source-posts/test/original-mode.jpg', 'original');
        SourcePostMedia::query()->create([
            'source_post_id' => $post->id,
            'position' => 0,
            'original_url' => 'https://instagram.test/original-mode.jpg',
            'url_hash' => hash('sha256', 'original-mode-image'),
            'file_path' => 'source-posts/test/original-mode.jpg',
            'mime_type' => 'image/jpeg',
        ]);
        $task->update([
            'source_post_id' => $post->id,
            'payload' => [
                ...$task->payload,
                'source_post_ids' => [$post->id],
            ],
        ]);
        $article = $user->aiArticles()->create([
            'source_post_ids' => [$post->id],
            'ai_prompt_profile_id' => $profile->id,
            'title' => 'Post recreado con IA',
            'content' => '<p>Contenido recreado.</p>',
            'status' => AiArticle::STATUS_DRAFT,
        ]);
        $articles = Mockery::mock(AiArticleService::class);
        $articles->shouldReceive('generateTextDraft')
            ->once()
            ->andReturnUsing(function ($taskUser, $taskProfile, $sourcePosts, $onPrepared) use ($article) {
                $onPrepared($article);

                return $article;
            });

        (new GenerateAiArticle($task->id))->handle(
            $articles,
            app(SchedulerService::class),
            app(OriginalPostImageService::class),
        );

        $this->assertSame(Scheduler::STATUS_COMPLETED, $task->fresh()->status);
        $this->assertDatabaseHas('ai_images', [
            'ai_article_id' => $article->id,
            'type' => AiImage::TYPE_MAIN,
            'model' => 'original',
            'status' => AiImage::STATUS_GENERATED,
        ]);
        Queue::assertNotPushed(GenerateAiImage::class);
    }

    public function test_the_same_task_cannot_generate_two_drafts_concurrently(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $profile = app(AiPromptProfileService::class)->ensureDefaultFor($user);
        $task = app(SchedulerService::class)->createQuickPostTask(
            $user,
            $profile,
            'https://x.com/example/status/123456',
            'original',
        );
        $lock = Cache::lock("scheduler:generate-ai-article:{$task->id}", 300);
        $this->assertTrue($lock->get());
        $articles = Mockery::mock(AiArticleService::class);
        $articles->shouldNotReceive('generateTextDraft');

        try {
            (new GenerateAiArticle($task->id))->handle(
                $articles,
                app(SchedulerService::class),
                app(OriginalPostImageService::class),
            );
        } finally {
            $lock->release();
        }

        $this->assertDatabaseCount('ai_articles', 0);
        $this->assertSame(Scheduler::STATUS_QUEUED, $task->fresh()->status);
    }
}
