<?php

namespace App\Http\Controllers;

use App\Models\AiImage;
use App\Services\Publications\InstagramImageTransformer;
use App\Services\Publications\InstagramMediaUrl;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PublicationMediaController extends Controller
{
    public function __construct(
        private readonly InstagramImageTransformer $images,
        private readonly InstagramMediaUrl $mediaUrl,
    ) {}

    public function __invoke(AiImage $aiImage, string $expires, string $token): Response
    {
        abort_unless($this->mediaUrl->isValid($aiImage, $expires, $token), 403, 'El enlace temporal de la imagen no es válido o ya expiró.');
        abort_unless($aiImage->file_path && Storage::disk('local')->exists($aiImage->file_path), 404);

        $contents = $this->images->jpeg($aiImage);

        return response($contents, 200, [
            'Content-Type' => 'image/jpeg',
            'Content-Length' => (string) strlen($contents),
            'Content-Disposition' => 'inline; filename="instagram-'.$aiImage->id.'.jpg"',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
