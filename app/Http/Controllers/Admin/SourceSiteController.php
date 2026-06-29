<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class SourceSiteController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.modules.show', [
            'title' => 'Sitios Fuente',
        ]);
    }
}
