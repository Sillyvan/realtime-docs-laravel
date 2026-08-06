<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Models\DocumentMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * @phpstan-type InvitationRow array{id: int, role: string, invited_at_for_humans: string|null, document: array{uuid: string, title: string}, invited_by: array{name: string}|null}
 */
class InvitationController extends Controller
{
    /**
     * List the invitations awaiting the user's response.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('invitations/index', [
            'invitations' => fn (): array => $this->pendingInvitationsFor($request),
        ]);
    }

    /**
     * Accept an invitation and open the document it grants access to.
     */
    public function update(Request $request, string $invitation): RedirectResponse
    {
        $membership = $this->pendingInvitation($request, $invitation);

        $membership->update([
            'status' => MembershipStatus::Accepted,
            'responded_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation accepted.')]);

        return to_route('documents.show', $membership->document);
    }

    /**
     * Decline an invitation.
     *
     * The row survives as `declined` so the owner can invite the user again.
     */
    public function destroy(Request $request, string $invitation): RedirectResponse
    {
        $this->pendingInvitation($request, $invitation)->update([
            'status' => MembershipStatus::Declined,
            'responded_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation declined.')]);

        return back();
    }

    /**
     * Resolve one of the authenticated user's pending invitations, or 404.
     */
    private function pendingInvitation(Request $request, string $id): DocumentMembership
    {
        return $request->user()->pendingInvitations()->findOrFail($id);
    }

    /**
     * Shape the user's pending invitations for the list.
     *
     * @return array<int, InvitationRow>
     */
    private function pendingInvitationsFor(Request $request): array
    {
        return $request->user()
            ->pendingInvitations()
            ->with(['document:id,uuid,title', 'invitedBy:id,name'])
            ->latest('id')
            ->get()
            ->map(fn (DocumentMembership $invitation): array => $this->toInvitationRow($invitation))
            ->all();
    }

    /**
     * Shape a pending invitation for the list.
     *
     * @return InvitationRow
     */
    private function toInvitationRow(DocumentMembership $invitation): array
    {
        return [
            'id' => $invitation->id,
            'role' => $invitation->role->value,
            'invited_at_for_humans' => $invitation->created_at?->diffForHumans(),
            'document' => [
                'uuid' => $invitation->document->uuid,
                'title' => $invitation->document->title,
            ],
            'invited_by' => $invitation->invitedBy === null
                ? null
                : ['name' => $invitation->invitedBy->name],
        ];
    }
}
