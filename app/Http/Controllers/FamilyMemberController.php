<?php

namespace App\Http\Controllers;

use App\Models\FamilyMember;
use App\Models\FamilyRelationship;
use App\Models\RelationshipType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class FamilyMemberController extends Controller
{
    public function index(Request $request)
    {
        $team = $request->user()->currentTeam;

        $members = FamilyMember::forTeam($team->id)
            ->withCount('timelineEntries')
            ->orderBy('first_name')
            ->get();

        return Inertia::render('FamilyMembers/Index', [
            'members' => $members,
            'relationshipTypes' => RelationshipType::groupedForTeam($team->id),
        ]);
    }

    public function create()
    {
        $team = auth()->user()->currentTeam;
        $user = auth()->user();

        $existingMembers = FamilyMember::forTeam($team->id)
            ->select('id', 'first_name', 'last_name', 'nickname', 'user_id')
            ->orderBy('first_name')
            ->get();

        // Find the current user's family member record (if they have one)
        $currentUserMember = FamilyMember::forTeam($team->id)
            ->where('user_id', $user->id)
            ->first();

        return Inertia::render('FamilyMembers/Create', [
            'existingMembers' => $existingMembers,
            'relationshipTypes' => RelationshipType::groupedForTeam($team->id),
            'currentUserMember' => $currentUserMember,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'nickname' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'death_date' => 'nullable|date|after_or_equal:birth_date',
            'gender' => 'nullable|in:male,female,other',
            'notes' => 'nullable|string',
            'is_living' => 'boolean',
            'photo' => 'nullable|image|max:2048',
            'relationships' => 'nullable|array',
            'relationships.*.member_id' => 'required|exists:family_members,id',
            'relationships.*.type' => 'required|string',
            'link_to_me' => 'nullable|boolean',
        ]);

        $team = $request->user()->currentTeam;

        // Handle photo upload
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('family-photos', 'public');
        }

        // Link to current user if requested
        $userId = null;
        if (!empty($validated['link_to_me'])) {
            // Check if user already has a family member linked
            $existing = FamilyMember::forTeam($team->id)
                ->where('user_id', $request->user()->id)
                ->first();
            if (!$existing) {
                $userId = $request->user()->id;
            }
        }

        $member = FamilyMember::create([
            'team_id' => $team->id,
            'user_id' => $userId,
            'created_by' => $request->user()->id,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'] ?? null,
            'nickname' => $validated['nickname'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'death_date' => $validated['death_date'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_living' => $validated['is_living'] ?? true,
            'photo_path' => $photoPath,
        ]);

        // Create relationships
        if (!empty($validated['relationships'])) {
            foreach ($validated['relationships'] as $rel) {
                $relatedMember = FamilyMember::find($rel['member_id']);
                if ($relatedMember) {
                    FamilyRelationship::createBidirectional($member, $relatedMember, $rel['type']);
                }
            }
        }

        // Return JSON for AJAX requests (quick-create from timeline form)
        // But NOT for Inertia requests which also use AJAX
        if (($request->wantsJson() || $request->ajax()) && !$request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'member' => [
                    'id' => $member->id,
                    'first_name' => $member->first_name,
                    'last_name' => $member->last_name,
                    'nickname' => $member->nickname,
                    'display_name' => $member->display_name,
                    'photo_path' => $member->photo_path,
                ],
            ]);
        }

        return redirect()->route('family-members.show', $member)
            ->with('message', 'Family member added successfully.');
    }

    public function show(FamilyMember $familyMember)
    {
        $this->authorizeTeamAccess($familyMember);

        $familyMember->load([
            'timelineEntries' => function ($q) {
                $q->orderBy('event_date', 'desc')->limit(20);
            },
            'allRelatedMembers',
        ]);

        $familyMember->loadCount('timelineEntries');

        $team = auth()->user()->currentTeam;

        return Inertia::render('FamilyMembers/Show', [
            'member' => $familyMember,
            'relationshipTypes' => RelationshipType::groupedForTeam($team->id),
        ]);
    }

    public function edit(FamilyMember $familyMember)
    {
        $this->authorizeTeamAccess($familyMember);

        $team = auth()->user()->currentTeam;
        $user = auth()->user();

        // Find the current user's family member record (if they have one)
        $currentUserMember = FamilyMember::forTeam($team->id)
            ->where('user_id', $user->id)
            ->where('id', '!=', $familyMember->id) // Exclude if editing yourself
            ->first();

        $existingMembers = FamilyMember::forTeam($team->id)
            ->where('id', '!=', $familyMember->id)
            ->when($currentUserMember, fn($q) => $q->where('id', '!=', $currentUserMember->id)) // Exclude current user (shown separately)
            ->select('id', 'first_name', 'last_name', 'nickname')
            ->orderBy('first_name')
            ->get();

        $familyMember->load(['allRelatedMembers']);

        return Inertia::render('FamilyMembers/Edit', [
            'member' => $familyMember,
            'existingMembers' => $existingMembers,
            'relationshipTypes' => RelationshipType::groupedForTeam($team->id),
            'currentUserMember' => $currentUserMember,
        ]);
    }

    public function update(Request $request, FamilyMember $familyMember)
    {
        $this->authorizeTeamAccess($familyMember);

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'nickname' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'death_date' => 'nullable|date|after_or_equal:birth_date',
            'gender' => 'nullable|in:male,female,other',
            'notes' => 'nullable|string',
            'is_living' => 'boolean',
            'photo' => 'nullable|image|max:2048',
        ]);

        // Handle photo upload
        if ($request->hasFile('photo')) {
            // Delete old photo
            if ($familyMember->photo_path) {
                Storage::disk('public')->delete($familyMember->photo_path);
            }
            $validated['photo_path'] = $request->file('photo')->store('family-photos', 'public');
        }

        $familyMember->update($validated);

        return redirect()->route('family-members.show', $familyMember)
            ->with('message', 'Family member updated successfully.');
    }

    public function destroy(FamilyMember $familyMember)
    {
        $this->authorizeTeamAccess($familyMember);

        // Delete photo if exists
        if ($familyMember->photo_path) {
            Storage::disk('public')->delete($familyMember->photo_path);
        }

        $familyMember->delete();

        return redirect()->route('family-members.index')
            ->with('message', 'Family member removed.');
    }

    /**
     * Search family members (API endpoint for autocomplete).
     */
    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:1',
        ]);

        $team = $request->user()->currentTeam;
        $query = $request->input('q');

        $members = FamilyMember::forTeam($team->id)
            ->search($query)
            ->limit(10)
            ->get(['id', 'first_name', 'last_name', 'nickname', 'photo_path']);

        return response()->json([
            'members' => $members,
        ]);
    }

    /**
     * Display the family tree visualization.
     */
    public function tree(Request $request)
    {
        $team = $request->user()->currentTeam;

        $members = FamilyMember::forTeam($team->id)
            ->with(['parents', 'children', 'spouses', 'siblings', 'allRelatedMembers'])
            ->get();

        return Inertia::render('FamilyMembers/Tree', [
            'members' => $members,
            'relationshipTypes' => RelationshipType::groupedForTeam($team->id),
        ]);
    }

    /**
     * Link a family member to the current user's account.
     */
    public function linkToUser(FamilyMember $familyMember)
    {
        $this->authorizeTeamAccess($familyMember);

        $user = auth()->user();
        $team = $user->currentTeam;

        // Check if user already has a linked family member in this team
        $existingLink = FamilyMember::forTeam($team->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingLink) {
            return back()->with('error', 'You are already linked to ' . $existingLink->display_name);
        }

        // Link this family member to the user
        $familyMember->update(['user_id' => $user->id]);

        return back()->with('message', 'Successfully linked to your account!');
    }

    /**
     * Unlink a family member from the current user's account.
     */
    public function unlinkFromUser(FamilyMember $familyMember)
    {
        $this->authorizeTeamAccess($familyMember);

        $user = auth()->user();

        // Verify this family member is linked to the current user
        if ($familyMember->user_id !== $user->id) {
            return back()->with('error', 'This family member is not linked to your account.');
        }

        // Unlink
        $familyMember->update(['user_id' => null]);

        return back()->with('message', 'Successfully unlinked from your account.');
    }

    private function authorizeTeamAccess(FamilyMember $member): void
    {
        $teamId = auth()->user()->currentTeam->id;

        if ($member->team_id !== $teamId) {
            abort(403, 'You do not have access to this family member.');
        }
    }
}
