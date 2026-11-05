<?php

namespace App\Http\Controllers;

use App\Models\AiImage;
use App\Services\Publications\InstagramVideoUrl;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicationVideoController extends Controller
{
    public function __construct(
        private readonly InstagramVideoUrl $videoUrl,
    ) {}

    public function __invoke(AiImage $aiImage, string $expires, string $token): BinaryFileResponse
    {
        abort_unless($this->videoUrl->isValid($aiImage, $expires, $token), 403, 'El enlace temporal del video no es válido o ya expiró.');
        abort_unless(str_starts_with((string) $aiImage->mime_type, 'video/'), 404);
        abort_unless($aiImage->file_path && Storage::disk('local')->exists($aiImage->file_path), 404);

        return response()->file(Storage::disk('local')->path($aiImage->file_path), [
            'Content-Type' => $aiImage->mime_type,
            'Content-Disposition' => 'inline; filename="instagram-video-'.$aiImage->id.'.mp4"',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
