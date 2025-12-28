<?php

use App\Http\Controllers\Api\TimelineParserController;
use App\Http\Controllers\FamilyMemberController;
use App\Http\Controllers\FamilyRelationshipController;
use App\Http\Controllers\TimelineEntryController;
use App\Models\TimelineEntry;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        $user = auth()->user();
        $team = $user->currentTeam;

        $totalEntries = TimelineEntry::where('team_id', $team->id)->count();
        $thisMonth = TimelineEntry::where('team_id', $team->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $recentEntries = TimelineEntry::where('team_id', $team->id)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalEntries' => $totalEntries,
                'thisMonth' => $thisMonth,
                'teamMembers' => $team->allUsers()->count(),
            ],
            'recentEntries' => $recentEntries,
        ]);
    })->name('dashboard');

    Route::resource('timeline', TimelineEntryController::class)->parameters(['timeline' => 'entry']);

    // Family Members
    Route::resource('family-members', FamilyMemberController::class);
    Route::post('family-members/{familyMember}/link', [FamilyMemberController::class, 'linkToUser'])->name('family-members.link');
    Route::post('family-members/{familyMember}/unlink', [FamilyMemberController::class, 'unlinkFromUser'])->name('family-members.unlink');
    Route::get('family-tree', [FamilyMemberController::class, 'tree'])->name('family.tree');
    Route::get('family-relationships', [FamilyRelationshipController::class, 'index'])->name('family-relationships.index');
    Route::post('family-relationships', [FamilyRelationshipController::class, 'storeGeneral'])->name('family-relationships.store-general');
    Route::post('family-members/{familyMember}/relationships', [FamilyRelationshipController::class, 'store'])->name('family-relationships.store');
    Route::delete('family-relationships/{familyRelationship}', [FamilyRelationshipController::class, 'destroy'])->name('family-relationships.destroy');

    // API endpoints
    Route::get('/api/family-members/search', [FamilyMemberController::class, 'search'])->name('api.family-members.search');

    // AI parsing API
    Route::post('/api/timeline/parse', [TimelineParserController::class, 'parse'])->name('api.timeline.parse');
    Route::post('/api/timeline/follow-up', [TimelineParserController::class, 'followUp'])->name('api.timeline.follow-up');
    Route::post('/api/timeline/check-backfill', [TimelineParserController::class, 'checkBackfill'])->name('api.timeline.check-backfill');
});
