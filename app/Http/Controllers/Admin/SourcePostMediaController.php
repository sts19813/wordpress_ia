<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SourcePostMedia;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SourcePostMediaController extends Controller
{
    public function __invoke(SourcePostMedia $sourcePostMedia): BinaryFileResponse
    {
        abort_unless(
            $sourcePostMedia->file_path && Storage::disk('local')->exists($sourcePostMedia->file_path),
            404,
        );

        return response()->file(Storage::disk('local')->path($sourcePostMedia->file_path), [
            'Content-Type' => $sourcePostMedia->mime_type ?: 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
