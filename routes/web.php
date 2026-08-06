<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AiArticleController;
use App\Http\Controllers\Admin\AiImageController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\NewsController;
use App\Http\Controllers\Admin\PublicationController;
use App\Http\Controllers\Admin\QuickPostController;
use App\Http\Controllers\Admin\SchedulerController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SourcePostMediaController;
use App\Http\Controllers\Admin\SourceScanLogController;
use App\Http\Controllers\Admin\SourceSiteController;
use App\Http\Controllers\Admin\SystemLogController;
use App\Http\Controllers\Admin\WordPressSiteController;
use App\Http\Controllers\Admin\XOAuthController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\PublicationMediaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('admin.dashboard')
        : redirect()->route('login');
});

Route::get('medios-publicacion/{aiImage}', PublicationMediaController::class)
    ->middleware('signed')
    ->name('publication-media.show');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');

    Route::get('auth/google', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
    Route::get('google-auth/callback', [GoogleAuthController::class, 'callback']);
});

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('auth/x/callback', [XOAuthController::class, 'callback'])
    ->middleware('auth')
    ->name('x-oauth.callback');

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('noticias', [NewsController::class, 'index'])->name('news.index');
    Route::get('post-rapido', [QuickPostController::class, 'index'])->name('quick-posts.index');
    Route::get('post-rapido/nuevo', [QuickPostController::class, 'create'])->name('quick-posts.create');
    Route::post('post-rapido', [QuickPostController::class, 'store'])->name('quick-posts.store');
    Route::delete('post-rapido/{sourcePost}', [QuickPostController::class, 'destroy'])->name('quick-posts.destroy');
    Route::get('medios-originales/{sourcePostMedia}', SourcePostMediaController::class)->name('source-post-media.file');
    Route::get('bitacora-fuentes', SourceScanLogController::class)->name('source-scan-logs.index');
    Route::delete('bitacora-fuentes', [SourceScanLogController::class, 'destroy'])->name('source-scan-logs.destroy');
    Route::post('noticias/obtener', [NewsController::class, 'fetch'])->name('news.fetch');
    Route::delete('noticias/{sourcePost}', [NewsController::class, 'destroy'])->name('news.destroy');
    Route::get('noticias/{sourcePost}', [NewsController::class, 'show'])->name('news.show');
    Route::post('sitios-fuente/probar', [SourceSiteController::class, 'test'])->name('source-sites.test');
    Route::resource('sitios-fuente', SourceSiteController::class)
        ->names('source-sites')
        ->parameters(['sitios-fuente' => 'sourceSite'])
        ->except('show');
    Route::resource('articulos-ia', AiArticleController::class)
        ->names('ai-articles')
        ->parameters(['articulos-ia' => 'aiArticle']);
    Route::get('imagenes-ia', [AiImageController::class, 'index'])->name('ai-images.index');
    Route::get('imagenes-ia/{aiImage}/archivo', [AiImageController::class, 'file'])->name('ai-images.file');
    Route::get('publicaciones', [PublicationController::class, 'index'])->name('publications.index');
    Route::put('empresas/{company}/destinos', [CompanyController::class, 'updateDestinations'])
        ->name('companies.destinations.update');
    Route::resource('empresas', CompanyController::class)
        ->names('companies')
        ->parameters(['empresas' => 'company'])
        ->except('show');
    Route::post('publicaciones/articulos/{aiArticle}', [PublicationController::class, 'publish'])->name('publications.publish');
    Route::resource('publicaciones/sitios-wordpress', WordPressSiteController::class)
        ->names('wordpress-sites')
        ->parameters(['sitios-wordpress' => 'wordpressSite'])
        ->except('show');
    Route::post('publicaciones/sitios-wordpress/{wordpressSite}/probar', [WordPressSiteController::class, 'test'])
        ->name('wordpress-sites.test');
    Route::get('publicaciones/sitios-wordpress/{wordpressSite}/conectar-x', [XOAuthController::class, 'redirect'])
        ->name('x-oauth.redirect');
    Route::get('programador', [SchedulerController::class, 'index'])->name('scheduler.index');
    Route::get('programador/{scheduler}/estado', [SchedulerController::class, 'status'])->name('scheduler.status');
    Route::post('programador/{scheduler}/ejecutar', [SchedulerController::class, 'execute'])->name('scheduler.execute');
    Route::post('programador/{scheduler}/reintentar', [SchedulerController::class, 'retry'])->name('scheduler.retry');
    Route::delete('programador/{scheduler}', [SchedulerController::class, 'destroy'])->name('scheduler.destroy');
    Route::post('programador/fuentes/{sourceSite}/ejecutar', [SchedulerController::class, 'runSource'])->name('scheduler.sources.run');
    Route::get('logs', SystemLogController::class)->name('system-logs.index');
    Route::get('configuracion', [SettingController::class, 'index'])->name('settings.index');
    Route::get('configuracion/prompts/nuevo', [SettingController::class, 'create'])->name('settings.prompts.create');
    Route::post('configuracion/prompts', [SettingController::class, 'store'])->name('settings.prompts.store');
    Route::get('configuracion/prompts/{aiPromptProfile}/editar', [SettingController::class, 'edit'])->name('settings.prompts.edit');
    Route::put('configuracion/prompts/{aiPromptProfile}', [SettingController::class, 'update'])->name('settings.prompts.update');
    Route::delete('configuracion/prompts/{aiPromptProfile}', [SettingController::class, 'destroy'])->name('settings.prompts.destroy');
    Route::get('cuenta', [AccountController::class, 'edit'])->name('account.edit');
    Route::patch('cuenta', [AccountController::class, 'update'])->name('account.update');
    Route::put('cuenta/contrasena', [AccountController::class, 'updatePassword'])->name('account.password.update');
});
