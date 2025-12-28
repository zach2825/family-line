<?php

namespace App\Http\Controllers;

use App\Models\FamilyMember;
use App\Models\FamilyRelationship;
use App\Models\RelationshipType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FamilyRelationshipController extends Controller
{
    public function index(Request $request)
    {
        $team = $request->user()->currentTeam;
        $user = $request->user();

        $members = FamilyMember::forTeam($team->id)
            ->select('id', 'first_name', 'last_name', 'nickname', 'user_id', 'photo_path')
            ->orderBy('first_name')
            ->get()
            ->map(fn($m) => [
                'id' => $m->id,
                'first_name' => $m->first_name,
                'last_name' => $m->last_name,
                'nickname' => $m->nickname,
                'display_name' => $m->display_name,
                'photo_path' => $m->photo_path,
                'is_me' => $m->user_id === $user->id,
            ]);

        // Get all relationships for this team (deduplicated - only show each pair once)
        $relationships = FamilyRelationship::whereHas('member', fn($q) => $q->where('team_id', $team->id))
            ->whereHas('relatedMember') // Ensure related member exists
            ->whereColumn('family_member_id', '<', 'related_member_id') // Only get one direction
            ->with(['member:id,first_name,last_name,nickname', 'relatedMember:id,first_name,last_name,nickname'])
            ->get()
            ->filter(fn($r) => $r->member && $r->relatedMember) // Extra safety filter
            ->map(fn($r) => [
                'id' => $r->id,
                'member_id' => $r->family_member_id,
                'member_name' => $r->member->display_name,
                'related_member_id' => $r->related_member_id,
                'related_member_name' => $r->relatedMember->display_name,
                'relationship_type' => $r->relationship_type,
            ])
            ->values();

        return Inertia::render('FamilyMembers/RelationshipManager', [
            'members' => $members,
            'relationships' => $relationships,
            'relationshipTypes' => RelationshipType::groupedForTeam($team->id),
        ]);
    }

    public function store(Request $request, FamilyMember $familyMember)
    {
        $this->authorizeTeamAccess($familyMember);

        // Get valid relationship type slugs
        $validTypes = RelationshipType::forTeam($familyMember->team_id)
            ->pluck('slug')
            ->toArray();

        $validated = $request->validate([
            'related_member_id' => 'required|exists:family_members,id|different:family_member_id',
            'relationship_type' => 'required|in:' . implode(',', $validTypes),
        ]);

        $relatedMember = FamilyMember::findOrFail($validated['related_member_id']);
        $this->authorizeTeamAccess($relatedMember);

        // Create bidirectional relationship
        FamilyRelationship::createBidirectional(
            $familyMember,
            $relatedMember,
            $validated['relationship_type']
        );

        return back()->with('message', 'Relationship added successfully.');
    }

    /**
     * Store a relationship from the relationship manager (both members selected).
     */
    public function storeGeneral(Request $request)
    {
        $team = $request->user()->currentTeam;

        // Get valid relationship type slugs
        $validTypes = RelationshipType::forTeam($team->id)
            ->pluck('slug')
            ->toArray();

        $validated = $request->validate([
            'member_id' => 'required|exists:family_members,id',
            'related_member_id' => 'required|exists:family_members,id|different:member_id',
            'relationship_type' => 'required|in:' . implode(',', $validTypes),
        ]);

        $member = FamilyMember::findOrFail($validated['member_id']);
        $relatedMember = FamilyMember::findOrFail($validated['related_member_id']);

        $this->authorizeTeamAccess($member);
        $this->authorizeTeamAccess($relatedMember);

        // Create bidirectional relationship
        FamilyRelationship::createBidirectional(
            $member,
            $relatedMember,
            $validated['relationship_type']
        );

        return back()->with('message', 'Relationship added successfully.');
    }

    public function destroy(FamilyRelationship $familyRelationship)
    {
        $member = $familyRelationship->member;
        $relatedMember = $familyRelationship->relatedMember;

        $this->authorizeTeamAccess($member);

        // Remove bidirectional relationship
        FamilyRelationship::removeBidirectional(
            $member,
            $relatedMember,
            $familyRelationship->relationship_type
        );

        return back()->with('message', 'Relationship removed.');
    }

    private function authorizeTeamAccess(FamilyMember $member): void
    {
        $teamId = auth()->user()->currentTeam->id;

        if ($member->team_id !== $teamId) {
            abort(403, 'You do not have access to this family member.');
        }
    }
}
