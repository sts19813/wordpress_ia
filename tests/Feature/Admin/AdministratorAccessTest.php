<?php

namespace Tests\Feature\Admin;

use App\Models\AiArticle;
use App\Models\User;
use App\Models\WordPressSite;
use App\Services\AiPromptProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministratorAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_see_global_companies_profiles_and_configuration(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $regularUser = User::factory()->create();
        $company = $owner->companies()->create([
            'name' => 'Empresa visible para administración',
            'active' => true,
        ]);
        $site = $owner->wordpressSites()->create([
            'company_id' => $company->id,
            'name' => 'Instagram de la empresa',
            'type' => WordPressSite::TYPE_INSTAGRAM,
            'instagram_account_id' => '17841440000000000',
            'instagram_access_token' => 'token',
            'status' => WordPressSite::STATUS_ACTIVE,
            'active' => true,
        ]);
        $profile = app(AiPromptProfileService::class)->ensureDefaultFor($owner);

        $this->actingAs($admin)
            ->get(route('admin.companies.index'))
            ->assertOk()
            ->assertSee($company->name)
            ->assertSee($owner->email);

        $this->actingAs($admin)
            ->get(route('admin.wordpress-sites.index'))
            ->assertOk()
            ->assertSee($site->name)
            ->assertSee($owner->email);

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee($profile->name)
            ->assertSee('Redes Sociales')
            ->assertDontSee($owner->email);

        $this->actingAs($regularUser)
            ->get(route('admin.companies.index'))
            ->assertDontSee($company->name);

        $this->actingAs($regularUser)
            ->get(route('admin.wordpress-sites.index'))
            ->assertDontSee($site->name);

        $this->actingAs($regularUser)
            ->get(route('admin.settings.index'))
            ->assertSee($profile->name)
            ->assertSee('Redes Sociales')
            ->assertDontSee($owner->email);
    }

    public function test_administrator_can_manage_records_owned_by_another_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $company = $owner->companies()->create([
            'name' => 'Empresa administrada',
            'active' => true,
        ]);
        $site = $owner->wordpressSites()->create([
            'company_id' => $company->id,
            'name' => 'Destino administrado',
            'type' => WordPressSite::TYPE_WORDPRESS,
            'rest_api_url' => 'https://destino.test',
            'username' => 'editor',
            'application_password' => 'secret',
            'status' => WordPressSite::STATUS_ACTIVE,
            'active' => true,
        ]);
        $profile = app(AiPromptProfileService::class)->ensureDefaultFor($owner);
        $article = $owner->aiArticles()->create([
            'company_id' => $company->id,
            'ai_prompt_profile_id' => $profile->id,
            'title' => 'Nota de otro usuario',
            'content' => '<p>Contenido administrable.</p>',
            'excerpt' => 'Contenido administrable.',
            'slug' => 'nota-de-otro-usuario',
            'status' => AiArticle::STATUS_DRAFT,
        ]);

        $this->actingAs($admin)->get(route('admin.companies.edit', $company))->assertOk();
        $this->actingAs($admin)->get(route('admin.wordpress-sites.edit', $site))->assertOk();
        $this->actingAs($admin)->get(route('admin.settings.prompts.edit', $profile))->assertOk();
        $this->actingAs($admin)
            ->get(route('admin.ai-articles.show', $article))
            ->assertOk();

        $this->actingAs($admin)
            ->put(route('admin.companies.destinations.update', $company), [
                'publication_profile_ids' => [$site->id],
            ])
            ->assertRedirect(route('admin.companies.edit', ['company' => $company, 'tab' => 'destinos']));

        $this->assertSame($owner->id, $site->fresh()->user_id);
        $this->assertSame($company->id, $site->company_id);
    }
}
