<?php

namespace Tests\Feature\QuickPosts;

use App\Jobs\CaptureQuickPost;
use App\Jobs\GenerateAiArticle;
use App\Models\Scheduler;
use App\Models\SourcePost;
use App\Models\SourcePostMedia;
use App\Models\User;
use App\Services\AiPromptProfileService;
use App\Services\QuickPosts\SocialPostCaptureService;
use App\Services\SchedulerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $response = $this->actingAs($user)->post(route('admin.quick-posts.store'), [
            'url' => $url,
        ]);

        $task = Scheduler::query()->sole();
        $response->assertRedirect(route('admin.scheduler.index', ['task' => $task->id]));
        $this->assertSame($url, $task->payload['url']);
        $this->assertSame([], $task->payload['source_post_ids']);
        Queue::assertPushedOn('social-capture', CaptureQuickPost::class);
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
}
