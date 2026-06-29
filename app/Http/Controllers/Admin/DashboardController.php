<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard.index', [
            'usersCount' => User::count(),
            'googleUsersCount' => User::whereNotNull('google_id')->count(),
            'recentUsersCount' => User::where('created_at', '>=', now()->subDays(30))->count(),
        ]);
    }
}
