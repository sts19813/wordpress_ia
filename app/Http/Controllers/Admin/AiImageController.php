<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiImage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AiImageController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AiImage::class);

        return view('admin.ai-images.index', [
            'images' => AiImage::query()
                ->whereHas('article', fn ($query) => $query->where('user_id', $request->user()->id))
                ->with('article:id,title,user_id')
                ->latest()
                ->get(),
        ]);
    }

    public function file(AiImage $aiImage): BinaryFileResponse
    {
        Gate::authorize('view', $aiImage);
        abort_unless($aiImage->file_path && Storage::disk('local')->exists($aiImage->file_path), 404);

        return response()->file(Storage::disk('local')->path($aiImage->file_path), [
            'Content-Type' => $aiImage->mime_type ?: 'image/png',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
