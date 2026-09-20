<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ViolatorController;
use App\Http\Controllers\ViolationController;
use App\Http\Controllers\CitationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\SearchController;

// ── Public ────────────────────────────────────────────────────
// Register the citizen host before routes that also work on localhost.
Route::domain(config('portals.citizen_domain'))->middleware('throttle:20,1')->group(function () {
    Route::get('/', [SearchController::class, 'index'])->name('citizen.search');
    Route::post('/lookup', [SearchController::class, 'lookup'])->middleware('throttle:citizen-lookup')->name('citizen.lookup');
    Route::post('/forget', [SearchController::class, 'forget'])->name('citizen.forget');
    Route::get('/records', [SearchController::class, 'results'])->name('citizen.results');
});
Route::view('/', 'landing')->name('home');
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login',[AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');
Route::post('/logout',[AuthController::class,'logout'])->name('logout')->middleware('auth');

// Public violation record search — no login required, rate limited
Route::middleware('throttle:20,1')->group(function () {
    Route::get('/search',            [SearchController::class, 'index'])->name('search');
    Route::post('/search/lookup', [SearchController::class, 'lookup'])->middleware('throttle:citizen-lookup')->name('search.lookup');
    Route::post('/search/forget', [SearchController::class, 'forget'])->name('search.forget');
    Route::get('/search/results', [SearchController::class, 'results'])->name('search.results');
});

// ── Authenticated (all roles) ─────────────────────────────────
Route::middleware(['auth'])->group(function () {
    // Admins monitor records and verify Treasury payments.
    // Enforcers submit records through the preview and confirmation flow.
    Route::middleware('role:admin')->group(function () {
        Route::post('/treasury-receipts/review', [CitationController::class, 'receiptDetails'])->name('receipts.review');
        Route::post('/violations/{violation}/reconcile-fine', [CitationController::class, 'reconcileFine'])->name('violations.reconcile-fine');
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/violators',            [ViolatorController::class, 'index'])->name('violators.index');
        Route::get('/violators/create',        fn () => abort(403, 'Record entry is limited to the enforcer submission flow; saved records cannot be edited or deleted.'))->name('violators.create');
        Route::get('/violators/{violator}', [ViolatorController::class, 'show'])->name('violators.show');
        Route::post('/violators',              fn () => abort(403, 'Record entry is limited to the enforcer submission flow; saved records cannot be edited or deleted.'))->name('violators.store');
        Route::get('/violators/{violator}/edit',fn () => abort(403, 'Record entry is limited to the enforcer submission flow; saved records cannot be edited or deleted.'))->name('violators.edit');
        Route::put('/violators/{violator}',    fn () => abort(403, 'Record entry is limited to the enforcer submission flow; saved records cannot be edited or deleted.'))->name('violators.update');
        Route::delete('/violators/{violator}', fn () => abort(403, 'Record entry is limited to the enforcer submission flow; saved records cannot be edited or deleted.'))->name('violators.destroy');

        Route::get('/violations',                  [ViolationController::class, 'index'])->name('violations.index');
        Route::post('/violations/{violation}/reverse-payment', [CitationController::class, 'reversePayment'])->name('violations.reverse-payment');
        Route::post('/violations/{violation}/verify-payment', [CitationController::class, 'verifyPayment'])->middleware('role:admin')->name('violations.verify-payment');
        Route::get('/violations/{violation}/edit', fn () => abort(403, 'Record entry is limited to the enforcer submission flow; saved records cannot be edited or deleted.'))->name('violations.edit');
        Route::put('/violations/{violation}',      fn () => abort(403, 'Record entry is limited to the enforcer submission flow; saved records cannot be edited or deleted.'))->name('violations.update');
        Route::delete('/violations/{violation}',   fn () => abort(403, 'Record entry is limited to the enforcer submission flow; saved records cannot be edited or deleted.'))->name('violations.destroy');

        Route::get('/citations',            [CitationController::class, 'index'])->name('citations.index');
        Route::get('/citations/{citation}', [CitationController::class, 'show'])->name('citations.show');
        Route::post('/citations/{citation}/pay', [CitationController::class, 'markPaid'])->middleware('role:admin')->name('citations.pay');

        Route::middleware('role:admin')->prefix('reports')->name('reports.')->group(function () {
            Route::get('/',                [ReportController::class, 'index'])->name('index');
            Route::get('/violations',      [ReportController::class, 'violations'])->name('violations');
            Route::get('/summary',         [ReportController::class, 'summary'])->name('summary');
            Route::get('/period', [ReportController::class, 'period'])->name('period');
        });
    });

    // Only enforcers record violations; admins verify Treasury payments.
    Route::get('/violations/create',      [ViolationController::class, 'create'])->middleware('role:enforcer')->name('violations.create');
    Route::post('/violations',            [ViolationController::class, 'store'])->middleware('role:enforcer')->name('violations.store')->block();
    // Viewing a single violation: admins can view any; the
    // controller restricts enforcers to only the ones they issued.
    Route::get('/violations/{violation}', [ViolationController::class, 'show'])->name('violations.show');

    Route::middleware('role:enforcer')->prefix('enforcer')->name('enforcer.')->group(function () {
        Route::get('/records', [ViolationController::class, 'mySubmissions'])->name('index');
        Route::post('/clear', [ViolationController::class, 'clearDraft'])->name('clear')->block();
        Route::get('/record', [ViolationController::class, 'create'])->name('create');
        Route::get('/records/{violation}', [ViolationController::class, 'show'])->name('show');
        Route::post('/preview', [ViolationController::class, 'previewEnforcer'])->name('preview')->block();
        Route::get('/preview', [ViolationController::class, 'reviewEnforcer'])->name('review');
        Route::post('/edit', [ViolationController::class, 'editEnforcer'])->name('edit')->block();
        Route::post('/confirm', [ViolationController::class, 'confirmEnforcer'])->name('confirm')->block();
    });

    // Admin only
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users',            [AdminController::class, 'users'])->name('users');
        Route::get('/users/create',     [AdminController::class, 'createUser'])->name('users.create');
        Route::post('/users',           [AdminController::class, 'storeUser'])->name('users.store');
        Route::get('/users/{user}/edit',[AdminController::class, 'editUser'])->name('users.edit');
        Route::put('/users/{user}',     [AdminController::class, 'updateUser'])->name('users.update');

        Route::get('/violation-types',  [AdminController::class, 'violationTypes'])->name('violation-types');
        Route::post('/violation-types', [AdminController::class, 'storeViolationType'])->name('violation-types.store');
        Route::put('/violation-types/{violationType}',[AdminController::class,'updateViolationType'])->name('violation-types.update');

        Route::delete('/violation-types/{violationType}', [AdminController::class, 'destroyViolationType'])->name('violation-types.destroy');

        Route::get('/audit-logs',       [AdminController::class, 'auditLogs'])->name('audit-logs');
    });
});
