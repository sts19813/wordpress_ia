<?php

namespace Tests\Feature\AiArticles;

use App\Models\AiPromptProfile;
use App\Models\User;
use App\Services\AiPromptProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiPromptProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_configuration_page_creates_a_default_editable_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Editorial general')
            ->assertSee('Perfiles de system prompt');

        $profile = AiPromptProfile::query()->sole();
        $this->assertTrue($profile->is_default);
        $this->assertSame($user->id, $profile->user_id);
    }

    public function test_user_cannot_edit_another_users_profile(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $profile = app(AiPromptProfileService::class)->ensureDefaultFor($owner);

        $this->actingAs($otherUser)
            ->get(route('admin.settings.prompts.edit', $profile))
            ->assertForbidden();
    }

    public function test_invalid_gpt_image_2_alias_is_normalized_and_not_offered(): void
    {
        $this->assertSame('gpt-image-2', AiPromptProfile::normalizeImageModel('gpt-image-2.0'));
        $this->assertArrayHasKey('gpt-image-2', AiPromptProfile::imageModelOptions());
        $this->assertArrayHasKey('gpt-image-2-2026-04-21', AiPromptProfile::imageModelOptions());
        $this->assertArrayNotHasKey('gpt-image-2.0', AiPromptProfile::imageModelOptions());
    }

    public function test_text_model_selector_offers_the_best_compact_models(): void
    {
        $options = AiPromptProfile::textModelOptions();

        $this->assertArrayHasKey('gpt-5.4-mini', $options);
        $this->assertArrayHasKey('gpt-5-mini', $options);
        $this->assertArrayHasKey('gpt-5.4-nano', $options);
        $this->assertArrayHasKey('gpt-4.1-mini', $options);
        $this->assertSame('gpt-4.1-mini', AiPromptProfile::normalizeTextModel('modelo-inventado'));
    }

    public function test_article_generation_form_contains_the_sweet_alert_loader(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.ai-articles.create'))
            ->assertOk()
            ->assertSee('Generando borrador con IA')
            ->assertSee('No cierres esta ventana.');
    }
}
