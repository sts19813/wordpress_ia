<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SourceSiteRequest;
use App\Models\SourceSite;
use App\Repositories\SourceSiteRepository;
use App\Services\NewsSources\SourceSiteTester;
use App\Services\SourceSiteService;
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
    ) {}

    public function index(Request $request): View
    {
        return view('admin.source-sites.index', [
            'sourceSites' => $this->sourceSites->getForAdmin($request->query()),
            'filterOptions' => $this->sourceSites->distinctFilterOptions(),
            'typeOptions' => SourceSite::typeOptions(),
            'statusOptions' => SourceSite::statusOptions(),
        ]);
    }

    public function create(): View
    {
        return view('admin.source-sites.create', [
            'sourceSite' => new SourceSite([
                'type' => SourceSite::TYPE_AUTO,
                'status' => SourceSite::STATUS_PENDING,
                'frequency_minutes' => 60,
                'language' => 'es',
                'priority' => 5,
                'auth_method' => SourceSite::AUTH_NONE,
                'daily_limit' => 20,
                'active' => true,
            ]),
            'typeOptions' => SourceSite::typeOptions(),
            'authMethodOptions' => SourceSite::authMethodOptions(),
        ]);
    }

    public function store(SourceSiteRequest $request): RedirectResponse
    {
        $this->sourceSiteService->create($request->validated());

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
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function edit(SourceSite $sourceSite): View
    {
        return view('admin.source-sites.edit', [
            'sourceSite' => $sourceSite,
            'typeOptions' => SourceSite::typeOptions(),
            'authMethodOptions' => SourceSite::authMethodOptions(),
        ]);
    }

    public function update(SourceSiteRequest $request, SourceSite $sourceSite): RedirectResponse
    {
        $this->sourceSiteService->update($sourceSite, $request->validated());

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
