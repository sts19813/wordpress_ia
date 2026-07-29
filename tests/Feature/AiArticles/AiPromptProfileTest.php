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

    public function test_profile_can_use_the_very_short_content_length(): void
    {
        $user = User::factory()->create();
        $profile = app(AiPromptProfileService::class)->ensureDefaultFor($user)->fresh();

        $this->actingAs($user)
            ->get(route('admin.settings.prompts.edit', $profile))
            ->assertOk()
            ->assertSee('Muy corto (150–200 palabras)');

        $this->actingAs($user)
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
