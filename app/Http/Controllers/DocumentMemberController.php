<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Http\Requests\Documents\MemberInviteRequest;
use App\Http\Requests\Documents\MemberRoleUpdateRequest;
use App\Models\Document;
use App\Models\DocumentMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DocumentMemberController extends Controller
{
    /**
     * Invite an account to the document by email.
     */
    public function store(MemberInviteRequest $request, Document $document): RedirectResponse
    {
        // updateOrCreate, so a declined invitation can be sent again on the same row.
        $document->memberships()->updateOrCreate(
            ['user_id' => $request->invitee()->id],
            [
                'role' => $request->validated('role'),
                'status' => MembershipStatus::Pending,
                'invited_by_id' => $request->user()->id,
                'responded_at' => null,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

        return back();
    }

    /**
     * Change what an existing member is allowed to do.
     */
    public function update(MemberRoleUpdateRequest $request, Document $document, DocumentMembership $membership): RedirectResponse
    {
        $membership->update($request->validated());

        return back();
    }

    /**
     * Revoke a member's access, or leave a document shared with you.
     */
    public function destroy(Request $request, Document $document, DocumentMembership $membership): RedirectResponse
    {
        $isLeaving = $membership->user_id === $request->user()->id;

        if (! $isLeaving) {
            Gate::authorize('manageMembers', $document);
        }

        $membership->delete();

        if (! $isLeaving) {
            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You left the document.')]);

        // Leaving costs the user their access, so there is no document to go back to.
        return to_route('documents.index');
    }
}
