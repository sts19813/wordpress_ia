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
}
