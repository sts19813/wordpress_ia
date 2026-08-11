<?php

namespace Tests\Feature\Companies;

use App\Jobs\CaptureQuickPost;
use App\Jobs\ScanSourceSite;
use App\Models\Company;
use App\Models\SourceSite;
use App\Models\User;
use App\Models\WordPressSite;
use App\Services\AiPromptProfileService;
use App\Services\SourcePipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CompanyModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_manage_companies_but_not_another_users_company(): void
    {
        $user = User::factory()->create();
        $otherCompany = User::factory()->create()->companies()->create([
            'name' => 'Empresa ajena',
            'active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('admin.companies.store'), [
                'name' => 'Grupo Peninsular',
                'description' => 'Marca de noticias regionales.',
                'active' => '1',
            ])
            ->assertRedirect(route('admin.companies.index'));

        $company = $user->companies()->sole();
        $this->assertSame('Grupo Peninsular', $company->name);
        $this->assertTrue($company->active);

        $this->actingAs($user)
            ->put(route('admin.companies.update', $otherCompany), [
                'name' => 'No autorizado',
                'active' => '1',
            ])
            ->assertForbidden();
    }

    public function test_source_configuration_accepts_destinations_from_multiple_companies(): void
    {
        $user = User::factory()->create();
        $prompt = app(AiPromptProfileService::class)->ensureDefaultFor($user);
        $company = $user->companies()->create(['name' => 'Empresa A', 'active' => true]);
        $otherCompany = $user->companies()->create(['name' => 'Empresa B', 'active' => true]);
        $profile = $this->publicationProfile($user, $company, 'WordPress A');
        $otherProfile = $this->publicationProfile($user, $otherCompany, 'Facebook B');

        $payload = $this->sourcePayload($prompt->id, $company->id, [$profile->id, $otherProfile->id]);
        $this->actingAs($user)
            ->post(route('admin.source-sites.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.source-sites.index'));

        $source = SourceSite::query()->sole();
        $this->assertSame($company->id, $source->company_id);
        $this->assertSame([$profile->id, $otherProfile->id], $source->publication_profile_ids);
    }

    public function test_company_destinations_tab_lists_the_catalog_and_profile_actions(): void
    {
        $user = User::factory()->create();
        $company = $user->companies()->create(['name' => 'Empresa A', 'active' => true]);
        $otherCompany = $user->companies()->create(['name' => 'Empresa B', 'active' => true]);
        $profile = $this->publicationProfile($user, $company, 'WordPress A');
        $otherProfile = $this->publicationProfile($user, $otherCompany, 'WordPress B');

        $this->actingAs($user)
            ->get(route('admin.companies.edit', ['company' => $company, 'tab' => 'destinos']))
            ->assertOk()
            ->assertSee('Catálogo de destinos')
            ->assertSee('WordPress A')
            ->assertSee('WordPress B')
            ->assertSee('Actualmente en Empresa B')
            ->assertSee(route('admin.wordpress-sites.edit', [
                'wordpressSite' => $otherProfile,
                'return_company' => $company->id,
            ]), false)
            ->assertSee(route('admin.wordpress-sites.test', $profile), false);
    }

    public function test_user_can_assign_move_and_remove_destinations_from_a_company(): void
    {
        $user = User::factory()->create();
        $company = $user->companies()->create(['name' => 'Empresa A', 'active' => true]);
        $otherCompany = $user->companies()->create(['name' => 'Empresa B', 'active' => true]);
        $removed = $this->publicationProfile($user, $company, 'Destino removido');
        $kept = $this->publicationProfile($user, $company, 'Destino conservado');
        $moved = $this->publicationProfile($user, $otherCompany, 'Destino movido');
        $source = SourceSite::query()->create([
            'name' => 'Fuente existente',
            'url' => 'https://fuente.test',
            'type' => SourceSite::TYPE_RSS,
            'status' => SourceSite::STATUS_ACTIVE,
            'frequency_minutes' => 60,
            'auth_method' => SourceSite::AUTH_NONE,
            'daily_limit' => 20,
            'automation_user_id' => $user->id,
            'company_id' => $company->id,
            'wordpress_site_id' => $removed->id,
            'publication_profile_ids' => [$removed->id, $kept->id],
            'active' => true,
        ]);

        $this->actingAs($user)
            ->put(route('admin.companies.destinations.update', $company), [
                'publication_profile_ids' => [$kept->id, $moved->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.companies.edit', ['company' => $company, 'tab' => 'destinos']));

        $this->assertNull($removed->fresh()->company_id);
        $this->assertSame($company->id, $kept->fresh()->company_id);
        $this->assertSame($company->id, $moved->fresh()->company_id);
        $this->assertSame([$kept->id], $source->fresh()->publication_profile_ids);
        $this->assertSame($kept->id, $source->fresh()->wordpress_site_id);

        $this->actingAs($user)
            ->put(route('admin.companies.destinations.update', $company))
            ->assertSessionHasNoErrors();

        $this->assertNull($kept->fresh()->company_id);
        $this->assertNull($moved->fresh()->company_id);
        $this->assertSame([], $source->fresh()->publication_profile_ids);
        $this->assertNull($source->fresh()->wordpress_site_id);
    }

    public function test_company_cannot_assign_another_users_destination(): void
    {
        $user = User::factory()->create();
        $company = $user->companies()->create(['name' => 'Empresa A', 'active' => true]);
        $profile = $this->publicationProfile($user, $company, 'Destino propio');
        $otherUser = User::factory()->create();
        $otherCompany = $otherUser->companies()->create(['name' => 'Empresa ajena', 'active' => true]);
        $otherProfile = $this->publicationProfile($otherUser, $otherCompany, 'Destino ajeno');

        $this->actingAs($user)
            ->put(route('admin.companies.destinations.update', $company), [
                'publication_profile_ids' => [$profile->id, $otherProfile->id],
            ])
            ->assertSessionHasErrors('publication_profile_ids.1');

        $this->assertSame($otherCompany->id, $otherProfile->fresh()->company_id);
    }

    public function test_quick_post_records_company_and_allows_its_selected_destinations(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $prompt = app(AiPromptProfileService::class)->ensureDefaultFor($user);
        $company = $user->companies()->create(['name' => 'Cliente Uno', 'active' => true]);
        $profiles = collect([
            $this->publicationProfile($user, $company, 'WordPress principal'),
            $this->publicationProfile($user, $company, 'Facebook principal'),
        ]);

        $this->actingAs($user)
            ->post(route('admin.quick-posts.store'), [
                'url' => 'https://x.com/openai/status/123456',
                'ai_prompt_profile_id' => $prompt->id,
                'image_mode' => 'original',
                'company_id' => $company->id,
                'publication_profile_ids' => $profiles->pluck('id')->all(),
            ])
            ->assertRedirect();

        $task = $user->scheduledTasks()->sole();
        $this->assertSame($company->id, $task->payload['company_id']);
        $this->assertSame($profiles->pluck('id')->all(), $task->payload['publication_profile_ids']);
        Queue::assertPushedOn('social-capture', CaptureQuickPost::class);
    }

    public function test_source_queue_copies_company_and_destinations_for_automatic_publication(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $company = $user->companies()->create(['name' => 'Marca automática', 'active' => true]);
        $profile = $this->publicationProfile($user, $company, 'WordPress automático');
        $prompt = app(AiPromptProfileService::class)->ensureDefaultFor($user);
        $source = SourceSite::query()->create([
            'name' => 'Fuente automática',
            'url' => 'https://source.test',
            'type' => SourceSite::TYPE_RSS,
            'status' => SourceSite::STATUS_ACTIVE,
            'frequency_minutes' => 60,
            'auth_method' => SourceSite::AUTH_NONE,
            'daily_limit' => 20,
            'automation_user_id' => $user->id,
            'ai_prompt_profile_id' => $prompt->id,
            'company_id' => $company->id,
            'publication_profile_ids' => [$profile->id],
            'auto_generate' => true,
            'auto_publish' => true,
            'active' => true,
        ]);

        $task = app(SourcePipelineService::class)->enqueueScan($source, 'manual', $user);

        $this->assertSame($company->id, $task->payload['company_id']);
        $this->assertSame([$profile->id], $task->payload['publication_profile_ids']);
        Queue::assertPushedOn('source-pipeline', ScanSourceSite::class);
    }

    private function publicationProfile(User $user, Company $company, string $name): WordPressSite
    {
        return $user->wordpressSites()->create([
            'company_id' => $company->id,
            'name' => $name,
            'type' => WordPressSite::TYPE_WORDPRESS,
            'rest_api_url' => 'https://'.str($name)->slug().'.test',
            'username' => 'editor',
            'application_password' => 'app-pass',
            'status' => WordPressSite::STATUS_ACTIVE,
            'active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function sourcePayload(int $promptId, int $companyId, array $profileIds): array
    {
        return [
            'name' => 'Fuente empresarial',
            'url' => 'https://example.com',
            'type' => SourceSite::TYPE_AUTO,
            'frequency_hours' => 1,
            'auth_method' => SourceSite::AUTH_NONE,
            'daily_limit' => 20,
            'max_posts_per_scan' => 10,
            'max_generations_per_scan' => 5,
            'ai_prompt_profile_id' => $promptId,
            'company_id' => $companyId,
            'publication_profile_ids' => $profileIds,
            'auto_generate' => '1',
            'auto_publish' => '1',
            'active' => '1',
        ];
    }
}
