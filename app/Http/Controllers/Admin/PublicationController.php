<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class PublicationController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.modules.show', [
            'title' => 'Publicaciones',
        ]);
    }
}
