<?php

namespace App\Http\Controllers;

use App\Models\AiImage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicationMediaController extends Controller
{
    public function __invoke(AiImage $aiImage): BinaryFileResponse
    {
        abort_unless($aiImage->file_path && Storage::disk('local')->exists($aiImage->file_path), 404);

        return response()->file(Storage::disk('local')->path($aiImage->file_path), [
            'Content-Type' => $aiImage->mime_type ?: 'image/png',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
