<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiPromptProfileRequest;
use App\Models\AiPromptProfile;
use App\Services\AiPromptProfileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SettingController extends Controller
{
    public function __construct(private readonly AiPromptProfileService $profiles) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AiPromptProfile::class);
        $this->profiles->ensureDefaultFor($request->user());

        return view('admin.settings.index', [
            'profiles' => $request->user()->aiPromptProfiles()->orderByDesc('is_default')->orderBy('name')->get(),
            'apiKeyConfigured' => filled(config('services.openai.api_key')),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', AiPromptProfile::class);

        return view('admin.settings.prompt-form', [
            'profile' => new AiPromptProfile([
                'system_prompt' => AiPromptProfile::DEFAULT_SYSTEM_PROMPT,
                'model' => config('services.openai.text_model', 'gpt-4.1-mini'),
                'temperature' => 0.7,
                'writing_style' => 'periodístico informativo',
                'tone' => 'claro, objetivo y profesional',
                'content_length' => 'medium',
                'language' => 'es',
                'audience' => 'público general',
                'max_output_tokens' => 4000,
                'generate_image' => true,
                'image_model' => config('services.openai.image_model', 'gpt-image-1.5'),
                'image_size' => '1536x1024',
                'image_quality' => 'medium',
                'image_style' => 'fotografía editorial realista, composición horizontal, sin texto incrustado',
            ]),
        ]);
    }

    public function store(AiPromptProfileRequest $request): RedirectResponse
    {
        Gate::authorize('create', AiPromptProfile::class);
        $this->profiles->create($request->user(), $request->validated());

        return redirect()->route('admin.settings.index')->with('status', 'Perfil de generación creado.');
    }

    public function edit(AiPromptProfile $aiPromptProfile): View
    {
        Gate::authorize('update', $aiPromptProfile);

        return view('admin.settings.prompt-form', ['profile' => $aiPromptProfile]);
    }

    public function update(AiPromptProfileRequest $request, AiPromptProfile $aiPromptProfile): RedirectResponse
    {
        Gate::authorize('update', $aiPromptProfile);
        $this->profiles->update($aiPromptProfile, $request->validated());

        return redirect()->route('admin.settings.index')->with('status', 'Perfil de generación actualizado.');
    }

    public function destroy(AiPromptProfile $aiPromptProfile): RedirectResponse
    {
        Gate::authorize('delete', $aiPromptProfile);
        abort_if($aiPromptProfile->articles()->exists(), 422, 'Este perfil ya fue utilizado y debe conservarse como referencia.');
        $this->profiles->delete($aiPromptProfile);

        return redirect()->route('admin.settings.index')->with('status', 'Perfil eliminado.');
    }
}
