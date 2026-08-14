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

    public function test_configuration_page_creates_two_global_profiles_visible_to_every_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Editorial general')
            ->assertSee('Redes Sociales')
            ->assertSee('2 perfiles globales para todo el sistema')
            ->assertDontSee('Nuevo perfil')
            ->assertSee('Perfiles de system prompt');

        $this->actingAs($otherUser)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Editorial general')
            ->assertSee('Redes Sociales');

        $this->assertDatabaseCount('ai_prompt_profiles', 2);
        $this->assertNull(AiPromptProfile::where('name', 'Editorial general')->sole()->user_id);
        $this->assertNull(AiPromptProfile::where('name', 'Redes Sociales')->sole()->user_id);
    }

    public function test_only_an_administrator_can_edit_a_global_profile(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $operator = User::factory()->create();
        $profile = app(AiPromptProfileService::class)->ensureDefaultFor($operator);

        $this->actingAs($operator)
            ->get(route('admin.settings.prompts.edit', $profile))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.settings.prompts.edit', $profile))
            ->assertOk();
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

    public function test_profile_can_use_the_very_short_content_length(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $profile = app(AiPromptProfileService::class)->ensureDefaultFor($admin)->fresh();

        $this->actingAs($admin)
            ->get(route('admin.settings.prompts.edit', $profile))
            ->assertOk()
            ->assertSee('Muy corto (150–200 palabras)');

        $this->actingAs($admin)
            ->put(route('admin.settings.prompts.update', $profile), [
                'name' => $profile->name,
                'system_prompt' => $profile->system_prompt,
                'model' => $profile->model,
                'temperature' => $profile->temperature,
                'writing_style' => $profile->writing_style,
                'tone' => $profile->tone,
                'content_length' => 'very_short',
                'language' => $profile->language,
                'audience' => $profile->audience,
                'max_output_tokens' => $profile->max_output_tokens,
                'generate_image' => '1',
                'image_model' => $profile->image_model,
                'image_size' => $profile->image_size,
                'image_quality' => $profile->image_quality,
                'image_format' => $profile->image_format,
                'image_compression' => $profile->image_compression,
                'image_style' => $profile->image_style,
                'is_default' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.settings.index'));

        $this->assertSame('very_short', $profile->fresh()->content_length);
    }

    public function test_article_generation_form_explains_background_queue_processing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.ai-articles.create'))
            ->assertOk()
            ->assertSee('cola en segundo plano')
            ->assertSee('Podrás cerrar la página');
    }
}
