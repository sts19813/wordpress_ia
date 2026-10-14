<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManualPostRequest;
use App\Http\Requests\QuickPostRequest;
use App\Http\Requests\ReviewGeneratedPostRequest;
use App\Models\AiImage;
use App\Models\AiPromptProfile;
use App\Models\Scheduler;
use App\Models\SourcePost;
use App\Models\SourcePostMedia;
use App\Services\AiPromptProfileService;
use App\Services\OpenAI\AudioTranscriptionService;
use App\Services\SchedulerService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ManualPostController extends Controller
{
    public function __construct(
        private readonly AiPromptProfileService $profiles,
        private readonly SchedulerService $scheduler,
    ) {}

    public function create(Request $request): View
    {
        $this->profiles->ensureDefaultFor($request->user());

        $companies = $request->user()->companies()
            ->where('active', true)
            ->with(['publicationProfiles' => fn ($query) => $query
                ->where('active', true)
                ->where('status', 'active')
                ->orderBy('name')])
            ->orderBy('name')
            ->get();

        $reviewTask = null;
        $reviewTaskId = (int) $request->query('review_task', 0);

        if ($reviewTaskId > 0) {
            $reviewTask = Scheduler::query()
                ->with('article.images')
                ->whereKey($reviewTaskId)
                ->when(! $request->user()->isAdmin(), fn ($query) => $query->where('user_id', $request->user()->id))
                ->first();

            if (! $reviewTask || ! (bool) data_get($reviewTask->payload, 'requires_review') || ! $reviewTask->article) {
                $reviewTask = null;
            }
        }

        return view('admin.manual-posts.create', [
            'profiles' => AiPromptProfile::query()
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
            'companies' => $companies,
            'activeMode' => in_array($request->query('mode'), ['url', 'voice'], true) ? $request->query('mode') : 'text',
            'reviewTask' => $reviewTask,
        ]);
    }

    public function store(ManualPostRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $profile = AiPromptProfile::query()->findOrFail($validated['ai_prompt_profile_id']);

        $sourcePost = DB::transaction(function () use ($request, $validated): SourcePost {
            $sourcePost = SourcePost::query()->create([
                'origin_type' => SourcePost::ORIGIN_QUICK_POST,
                'social_platform' => 'manual',
                'title' => $this->titleFromContent($validated['content']),
                'content' => $validated['content'],
                'summary' => Str::limit(preg_replace('/\s+/', ' ', $validated['content']), 280, ''),
                'url' => 'manual-post://'.Str::uuid(),
                'hash' => hash('sha256', $request->user()->id.'|'.Str::uuid().'|'.$validated['content']),
                'status' => SourcePost::STATUS_FETCHED,
                'original_json' => [
                    'origin' => 'manual_post',
                    'submitted_at' => now()->toIso8601String(),
                ],
                'language' => 'es',
                'captured_at' => now(),
            ]);

            foreach ($request->file('images', []) as $position => $image) {
                if ($image instanceof UploadedFile) {
                    $this->storeImage($sourcePost, $image, $position);
                }
            }

            return $sourcePost;
        });

        $publicationProfileIds = $request->publicationProfileIds();
        $companyIds = array_values(array_unique(array_map('intval', $validated['company_ids'] ?? [])));
        $task = $this->scheduler->createManualPostTask(
            $request->user(),
            $profile,
            $sourcePost,
            $publicationProfileIds,
            $companyIds,
        );

        $imageCount = $sourcePost->media()->count();
        $imageMessage = $imageCount > 0
            ? " Se usarán {$imageCount} imagen(es) subidas."
            : ' Podrás agregar imágenes al revisar el borrador.';
        $publicationCount = count($publicationProfileIds);
        $publicationMessage = $publicationCount > 0
            ? " Podrás revisarlo antes de publicarlo en {$publicationCount} destino(s)."
            : ' El resultado quedará como borrador.';

        $message = 'El post se añadió a la cola de generación.'.$imageMessage.$publicationMessage;

        if ($request->expectsJson()) {
            return response()->json([
                'task_id' => $task->id,
                'status_url' => route('admin.scheduler.status', $task),
                'message' => $message,
            ], 202);
        }

        return redirect()
            ->route('admin.scheduler.index', ['task' => $task->id])
            ->with('status', $message);
    }

    public function storeUrl(QuickPostRequest $request): RedirectResponse|JsonResponse
    {
        $profile = AiPromptProfile::query()->findOrFail($request->validated('ai_prompt_profile_id'));
        $companyIds = $request->companyIds();
        $publicationProfileIds = $request->publicationProfileIds();
        $task = $this->scheduler->createQuickPostTask(
            $request->user(),
            $profile,
            $request->validated('url'),
            $request->validated('image_mode'),
            $publicationProfileIds,
            count($companyIds) === 1 ? $companyIds[0] : null,
            true,
            $companyIds,
        );
        $imageMessage = $request->validated('image_mode') === 'original'
            ? 'conservando sus imágenes originales'
            : 'generando una imagen principal con IA';
        $publicationCount = count($publicationProfileIds);
        $publicationMessage = $publicationCount > 0
            ? " Podrás revisarlo antes de publicarlo en {$publicationCount} destino(s)."
            : ' El resultado quedará como borrador.';
        $message = "La captura y recreación con IA se añadieron a la cola, {$imageMessage}.{$publicationMessage}";

        if ($request->expectsJson()) {
            return response()->json([
                'task_id' => $task->id,
                'status_url' => route('admin.scheduler.status', $task),
                'message' => $message,
            ], 202);
        }

        return redirect()
            ->route('admin.scheduler.index', ['task' => $task->id])
            ->with('status', $message);
    }

    public function transcribe(Request $request, AudioTranscriptionService $transcriptions): JsonResponse
    {
        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:25600', 'mimes:mp3,mp4,m4a,wav,webm,mpeg,mpga,ogg'],
        ], [
            'audio.max' => 'El audio puede pesar máximo 25 MB.',
            'audio.mimes' => 'Usa un audio MP3, M4A, MP4, WAV, WebM u OGG.',
        ]);

        return response()->json([
            'text' => $transcriptions->transcribe($validated['audio']),
        ]);
    }

    public function updateReview(ReviewGeneratedPostRequest $request, Scheduler $scheduler): RedirectResponse
    {
        abort_unless($this->canManageTask($request, $scheduler), 403);
        abort_unless((bool) data_get($scheduler->payload, 'requires_review') && $scheduler->article, 404);
        abort_if((bool) data_get($scheduler->payload, 'review_approved'), 409);

        $this->syncReview($scheduler, $request);

        return redirect()
            ->route('admin.manual-posts.create', ['review_task' => $scheduler->id])
            ->with('status', 'Borrador actualizado.');
    }

    public function publish(ReviewGeneratedPostRequest $request, Scheduler $scheduler): RedirectResponse
    {
        abort_unless($this->canManageTask($request, $scheduler), 403);
        abort_unless((bool) data_get($scheduler->payload, 'requires_review'), 404);
        abort_if((bool) data_get($scheduler->payload, 'review_approved'), 409);

        $this->syncReview($scheduler, $request);
        try {
            $task = $this->scheduler->approvePublication($scheduler);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['publication_profile_ids' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.manual-posts.create', ['review_task' => $task->id])
            ->with('status', 'La publicación se añadió a la cola para todos los destinos seleccionados.');
    }

    private function titleFromContent(string $content): string
    {
        $firstLine = collect(preg_split('/\R+/', trim($content)))
            ->map(fn (string $line): string => trim($line))
            ->first(fn (string $line): bool => $line !== '');

        return Str::limit($firstLine ?: 'Post generado manualmente', 180, '');
    }

    private function storeImage(SourcePost $sourcePost, UploadedFile $image, int $position): void
    {
        $extension = strtolower($image->extension() ?: $image->getClientOriginalExtension() ?: 'jpg');
        $filename = Str::uuid().'.'.$extension;
        $path = $image->storeAs("source-post-media/manual/{$sourcePost->id}", $filename, 'local');
        $absolutePath = Storage::disk('local')->path($path);
        $size = @getimagesize($absolutePath);

        SourcePostMedia::query()->create([
            'source_post_id' => $sourcePost->id,
            'type' => 'image',
            'position' => $position,
            'original_url' => 'manual-upload://'.$image->getClientOriginalName(),
            'url_hash' => hash('sha256', $path.'|'.$position),
            'file_path' => $path,
            'mime_type' => $image->getMimeType(),
            'width' => is_array($size) ? (int) $size[0] : null,
            'height' => is_array($size) ? (int) $size[1] : null,
            'metadata' => [
                'origin' => 'manual_upload',
                'original_name' => $image->getClientOriginalName(),
                'size' => $image->getSize(),
            ],
        ]);
    }

    private function canManageTask(Request $request, Scheduler $task): bool
    {
        return $request->user()->isAdmin() || $task->user_id === $request->user()->id;
    }

    private function syncReview(Scheduler $task, ReviewGeneratedPostRequest $request): void
    {
        $companyIds = array_values(array_unique(array_map('intval', $request->validated('company_ids', []))));
        $this->scheduler->updateReview($task, $companyIds, $request->publicationProfileIds());
        $article = $task->article()->with('images')->firstOrFail();
        $mainImageExists = $article->images->contains(fn (AiImage $image): bool => $image->type === AiImage::TYPE_MAIN
            && $image->status === AiImage::STATUS_GENERATED);

        foreach ($request->file('images', []) as $position => $image) {
            if (! $image instanceof UploadedFile) {
                continue;
            }

            $extension = strtolower($image->extension() ?: $image->getClientOriginalExtension() ?: 'jpg');
            $path = $image->storeAs(
                "ai-images/{$article->id}/uploads",
                Str::uuid().'.'.$extension,
                'local',
            );
            $article->images()->create([
                'type' => $mainImageExists ? AiImage::TYPE_VARIANT : AiImage::TYPE_MAIN,
                'title' => $image->getClientOriginalName(),
                'prompt' => 'Imagen subida durante la revisión del borrador.',
                'model' => 'upload',
                'status' => AiImage::STATUS_GENERATED,
                'file_path' => $path,
                'mime_type' => $image->getMimeType(),
                'source_context' => [
                    'origin' => 'review_upload',
                    'position' => $position,
                    'original_name' => $image->getClientOriginalName(),
                ],
            ]);
            $mainImageExists = true;
        }
    }
}
