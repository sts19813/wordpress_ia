<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SourceSiteRequest;
use App\Models\Company;
use App\Models\SourceSite;
use App\Models\SystemLog;
use App\Models\WordPressSite;
use App\Repositories\SourceSiteRepository;
use App\Services\AiPromptProfileService;
use App\Services\NewsSources\SourceSiteTester;
use App\Services\SourceSiteService;
use App\Services\SystemLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class SourceSiteController extends Controller
{
    public function __construct(
        private readonly SourceSiteRepository $sourceSites,
        private readonly SourceSiteService $sourceSiteService,
        private readonly AiPromptProfileService $promptProfiles,
        private readonly SystemLogService $systemLogs,
    ) {}

    public function index(): View
    {
        return view('admin.source-sites.index', [
            'sourceSites' => $this->sourceSites->getForAdmin(),
        ]);
    }

    public function create(Request $request): View
    {
        $defaultProfile = $this->promptProfiles->ensureDefaultFor($request->user());
        $companies = Company::query()->where('active', true)->orderBy('name')->get();

        if ($companies->isEmpty()) {
            $companies = collect([$request->user()->companies()->create([
                'name' => 'Empresa principal',
                'description' => 'Empresa predeterminada para organizar tus publicaciones.',
                'active' => true,
            ])]);
        }

        $wordpressSites = WordPressSite::query()
            ->with('company:id,name')
            ->where('active', true)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $defaultCompanyId = $companies->count() === 1 ? $companies->first()->id : null;
        $defaultPublicationProfileIds = $defaultCompanyId
            ? $wordpressSites->where('company_id', $defaultCompanyId)->pluck('id')->values()->all()
            : [];

        return view('admin.source-sites.create', [
            'sourceSite' => new SourceSite([
                'automation_user_id' => $request->user()->id,
                'company_id' => $defaultCompanyId,
                'ai_prompt_profile_id' => $defaultProfile->id,
                'wordpress_site_id' => $defaultPublicationProfileIds[0] ?? null,
                'publication_profile_ids' => $defaultPublicationProfileIds,
                'auto_generate' => true,
                'auto_publish' => $defaultPublicationProfileIds !== [],
                'type' => SourceSite::TYPE_AUTO,
                'status' => SourceSite::STATUS_PENDING,
                'frequency_minutes' => 60,
                'language' => 'es',
                'priority' => 5,
                'auth_method' => SourceSite::AUTH_NONE,
                'daily_limit' => 20,
                'max_posts_per_scan' => 20,
                'max_generations_per_scan' => 5,
                'active' => true,
            ]),
            'typeOptions' => SourceSite::typeOptions(),
            'authMethodOptions' => SourceSite::authMethodOptions(),
            'promptProfiles' => $request->user()->aiPromptProfiles()->orderByDesc('is_default')->orderBy('name')->get(),
            'companies' => $companies,
            'wordpressSites' => $wordpressSites,
        ]);
    }

    public function store(SourceSiteRequest $request): RedirectResponse
    {
        $this->sourceSiteService->create([
            ...$request->validated(),
            'automation_user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.source-sites.index')
            ->with('status', 'Sitio fuente creado correctamente.');
    }

    public function test(Request $request, SourceSiteTester $tester): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url:http,https', 'max:2048'],
            'type' => ['required', Rule::in(array_keys(SourceSite::typeOptions()))],
            'auth_method' => ['nullable', Rule::in(array_keys(SourceSite::authMethodOptions()))],
            'api_key' => ['nullable', 'string', 'max:2048'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:2048'],
            'custom_headers' => ['nullable', 'json'],
            'cookies' => ['nullable', 'json'],
            'source_site_id' => ['nullable', 'integer', 'exists:source_sites,id'],
        ]);

        foreach (['custom_headers', 'cookies'] as $field) {
            $validated[$field] = filled($validated[$field] ?? null)
                ? json_decode((string) $validated[$field], true)
                : null;
        }

        if ($storedSourceSite = SourceSite::query()->find($validated['source_site_id'] ?? null)) {
            foreach (['api_key', 'password'] as $secret) {
                if (blank($validated[$secret] ?? null)) {
                    $validated[$secret] = $storedSourceSite->{$secret};
                }
            }
        }

        unset($validated['source_site_id']);

        try {
            return response()->json($tester->test(new SourceSite([
                ...$validated,
                'name' => $request->string('name')->toString() ?: 'Prueba temporal',
                'language' => 'es',
                'daily_limit' => 20,
                'auth_method' => $validated['auth_method'] ?? SourceSite::AUTH_NONE,
            ])));
        } catch (Throwable $exception) {
            $this->systemLogs->recordError(
                SystemLog::EVENT_SOURCE_FAILED,
                'Sitios fuente',
                $exception->getMessage(),
                context: ['url' => $validated['url']],
                userId: $request->user()?->id,
            );

            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function edit(Request $request, SourceSite $sourceSite): View
    {
        $owner = $sourceSite->automationUser ?: $request->user();

        return view('admin.source-sites.edit', [
            'sourceSite' => $sourceSite,
            'typeOptions' => SourceSite::typeOptions(),
            'authMethodOptions' => SourceSite::authMethodOptions(),
            'promptProfiles' => $owner->aiPromptProfiles()->orderByDesc('is_default')->orderBy('name')->get(),
            'companies' => Company::query()
                ->where(fn ($query) => $query->where('active', true)->orWhere('id', $sourceSite->company_id))
                ->orderBy('name')
                ->get(),
            'wordpressSites' => WordPressSite::query()
                ->with('company:id,name')
                ->where('active', true)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(SourceSiteRequest $request, SourceSite $sourceSite): RedirectResponse
    {
        $this->sourceSiteService->update($sourceSite, [
            ...$request->validated(),
            'automation_user_id' => $sourceSite->automation_user_id ?: $request->user()->id,
        ]);

        return redirect()
            ->route('admin.source-sites.index')
            ->with('status', 'Sitio fuente actualizado correctamente.');
    }

    public function destroy(SourceSite $sourceSite): RedirectResponse
    {
        $this->sourceSiteService->delete($sourceSite);

        return redirect()
            ->route('admin.source-sites.index')
            ->with('status', 'Sitio fuente eliminado correctamente.');
    }
}
