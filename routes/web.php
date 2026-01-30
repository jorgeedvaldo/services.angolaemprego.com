<?php

use App\Http\Controllers\JobController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LinkController;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\SocialPostController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\AutoApplicationController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    $pendingApplications = \App\Models\AutoApplication::where('status', 'pending')
        ->with(['user', 'trackedJob'])
        ->get();

    return view('welcome', compact('pendingApplications'));
});

Route::get('/ObterAngolaEmpregoAngoEmprego', [LinkController::class, 'ObterAngolaEmpregoAngoEmprego']);
Route::get('/ObterAngolaEmpregoAngoEmprego/{website}', [LinkController::class, 'ObterAngolaEmpregoAngoEmprego']);
Route::get('/Obter/{website}', [JobController::class, 'fetchFromWebsite']);
Route::get('/ObterPost', [PostController::class, 'fetchFromWebsite']);
Route::get('/PostOnMedia', [SocialPostController::class, 'postLastToMedia']);
Route::get('/fetch-match-jobs', [AutoApplicationController::class, 'fetchAndMatchJobs']);
Route::post('/applications/{autoApplication}/send', [AutoApplicationController::class, 'send'])->name('applications.send');
Route::post('/applications/{autoApplication}/fail', [AutoApplicationController::class, 'markAsFailed'])->name('applications.fail');
Route::post('/applications/bulk-update', [AutoApplicationController::class, 'bulkUpdate'])->name('applications.bulk-update');
