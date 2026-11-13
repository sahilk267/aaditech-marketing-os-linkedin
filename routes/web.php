<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class,'showLogin'])->name('login');
    Route::post('/login', [AuthController::class,'login'])->middleware('throttle:5,1')->name('login.attempt');
});
Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthController::class,'logout'])->name('logout');
    Route::resource('brands', BrandController::class)->except(['destroy']);
});

// Browser-session routes retain Laravel web CSRF middleware; no unauthenticated mutation APIs.
Route::middleware(['auth','throttle:90,1'])->prefix('api/v1/brands/{brand}')->group(function (): void {
    Route::get('/overview',[\App\Http\Controllers\MarketingApiController::class,'overview']);
    Route::get('/sources',[\App\Http\Controllers\MarketingApiController::class,'sources']);
    Route::post('/sources',[\App\Http\Controllers\MarketingApiController::class,'sourceCreate']);
    Route::post('/sources/{sourceId}/approve',[\App\Http\Controllers\MarketingApiController::class,'sourceApprove'])->whereNumber('sourceId');
    Route::post('/sources/{sourceId}/fetch',[\App\Http\Controllers\MarketingApiController::class,'researchQueue'])->whereNumber('sourceId');
    Route::get('/research',[\App\Http\Controllers\MarketingApiController::class,'research']);
    Route::post('/research/{itemId}/verify',[\App\Http\Controllers\MarketingApiController::class,'researchVerify'])->whereNumber('itemId');
    Route::get('/campaigns',[\App\Http\Controllers\MarketingApiController::class,'campaigns']);
    Route::post('/campaigns',[\App\Http\Controllers\MarketingApiController::class,'campaignCreate']);
    Route::get('/content',[\App\Http\Controllers\MarketingApiController::class,'content']);
    Route::post('/content/generate',[\App\Http\Controllers\MarketingApiController::class,'contentGenerate']);
    Route::post('/content/manual',[\App\Http\Controllers\ManualContentController::class,'store']);
    Route::patch('/content/{contentId}',[\App\Http\Controllers\ContentRevisionController::class,'update'])->whereNumber('contentId');
    Route::post('/content/{contentId}/claims',[\App\Http\Controllers\ClaimAttestationController::class,'attach'])->whereNumber('contentId');
    Route::post('/content/{contentId}/claims/attest',[\App\Http\Controllers\ClaimAttestationController::class,'attest'])->whereNumber('contentId');
    Route::post('/content/{contentId}/approve',[\App\Http\Controllers\MarketingApiController::class,'contentApprove'])->whereNumber('contentId');
    Route::post('/content/{contentId}/render',[\App\Http\Controllers\MarketingApiController::class,'render'])->whereNumber('contentId');
    Route::post('/content/{contentId}/schedule',[\App\Http\Controllers\MarketingApiController::class,'schedule'])->whereNumber('contentId');
    Route::post('/content/{contentId}/manual-ready',[\App\Http\Controllers\ManualPublishingController::class,'store'])->whereNumber('contentId');
    Route::post('/content/{contentId}/render-offline',[\App\Http\Controllers\OfflineCreativeController::class,'store'])->whereNumber('contentId');
    Route::get('/calendar',[\App\Http\Controllers\MarketingApiController::class,'calendar']);
    Route::get('/publishing/reconciliation',[\App\Http\Controllers\PublicationReconciliationController::class,'index']);
    Route::post('/publishing/{id}/reconcile',[\App\Http\Controllers\PublicationReconciliationController::class,'resolve'])->whereNumber('id');
    Route::get('/manual-package/{publicationId}',[\App\Http\Controllers\MarketingApiController::class,'manualPackage'])->whereNumber('publicationId');
    Route::post('/analytics/import',[\App\Http\Controllers\MarketingApiController::class,'analyticsImport']);
    Route::get('/reports/weekly',[\App\Http\Controllers\MarketingApiController::class,'report']);
    Route::get('/social-accounts',[\App\Http\Controllers\SocialAccountController::class,'index']);
    Route::post('/social-accounts/{id}/approval',[\App\Http\Controllers\SocialAccountController::class,'approval'])->whereNumber('id');
});
Route::middleware('auth')->group(function(): void {
    Route::post('/brands/{brand}/linkedin/connect',[\App\Http\Controllers\LinkedInOAuthController::class,'connect']);
    Route::get('/auth/linkedin/callback',[\App\Http\Controllers\LinkedInOAuthController::class,'callback']);
    Route::post('/brands/{brand}/facebook/connect',[\App\Http\Controllers\FacebookPageOAuthController::class,'connect'])->middleware('throttle:5,1');
    Route::get('/auth/facebook/callback',[\App\Http\Controllers\FacebookPageOAuthController::class,'callback'])->middleware('throttle:10,1');
});
Route::get('/brands/{brand}/marketing',\App\Http\Controllers\MarketingDashboardController::class)->middleware('auth')->name('brands.marketing');
Route::get('/brands/{brand}/assets/{id}/download',\App\Http\Controllers\MediaDownloadController::class)
    ->middleware('auth')->whereNumber('id')->name('brands.assets.download');

Route::post('/api/v1/ai/sync-free-catalog',[\App\Http\Controllers\MarketingApiController::class,'syncFreeCatalog'])->middleware(['auth','throttle:5,1']);
