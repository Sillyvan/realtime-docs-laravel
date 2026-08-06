<?php

use App\Enums\MembershipStatus;
use App\Models\Document;
use App\Models\DocumentMembership;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the shared badge count only counts the user’s pending invitations', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    DocumentMembership::factory()->pending()->count(2)->create(['user_id' => $user->id]);
    DocumentMembership::factory()->accepted()->create(['user_id' => $user->id]);
    DocumentMembership::factory()->declined()->create(['user_id' => $user->id]);
    DocumentMembership::factory()->pending()->create(['user_id' => $other->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('pendingInvitationsCount', 2));

    $this->actingAs($other)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('pendingInvitationsCount', 1));
});

test('the index lists the pending invitations with their document, inviter and role', function () {
    $user = User::factory()->create();
    $owner = User::factory()->create(['name' => 'Ada']);
    $document = Document::factory()->create(['owner_id' => $owner->id, 'title' => 'Roadmap']);

    DocumentMembership::factory()->pending()->editor()->create([
        'document_id' => $document->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('invitations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('invitations/index')
            ->has('invitations', 1)
            ->where('invitations.0.role', 'editor')
            ->where('invitations.0.document.uuid', $document->uuid)
            ->where('invitations.0.document.title', 'Roadmap')
            ->where('invitations.0.invited_by.name', 'Ada'),
        );
});

test('the index hides invitations that are not pending, and other people’s', function () {
    $user = User::factory()->create();

    DocumentMembership::factory()->accepted()->create(['user_id' => $user->id]);
    DocumentMembership::factory()->declined()->create(['user_id' => $user->id]);
    DocumentMembership::factory()->pending()->create();

    $this->actingAs($user)
        ->get(route('invitations.index'))
        ->assertInertia(fn (Assert $page) => $page->has('invitations', 0));
});

test('accepting an invitation grants access and opens the document', function () {
    $user = User::factory()->create();
    $document = Document::factory()->create();
    $invitation = DocumentMembership::factory()->pending()->editor()->create([
        'document_id' => $document->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->patch(route('invitations.update', $invitation))
        ->assertRedirect(route('documents.show', $document));

    expect($invitation->fresh())
        ->status->toBe(MembershipStatus::Accepted)
        ->responded_at->not->toBeNull();

    $this->actingAs($user)->get(route('documents.show', $document))->assertOk();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('pendingInvitationsCount', 0));
});

test('declining an invitation keeps the row and grants no access', function () {
    $user = User::factory()->create();
    $document = Document::factory()->create();
    $invitation = DocumentMembership::factory()->pending()->editor()->create([
        'document_id' => $document->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->from(route('invitations.index'))
        ->delete(route('invitations.destroy', $invitation))
        ->assertRedirect(route('invitations.index'));

    // The row survives as declined so the owner can invite the user again.
    expect(DocumentMembership::count())->toBe(1)
        ->and($invitation->fresh())
        ->status->toBe(MembershipStatus::Declined)
        ->responded_at->not->toBeNull();

    $this->actingAs($user)->get(route('documents.show', $document))->assertForbidden();
});

test('an invitation addressed to somebody else cannot be acted on', function () {
    $stranger = User::factory()->create();
    $invitation = DocumentMembership::factory()->pending()->create();

    $this->actingAs($stranger)->patch(route('invitations.update', $invitation))->assertNotFound();
    $this->actingAs($stranger)->delete(route('invitations.destroy', $invitation))->assertNotFound();

    expect($invitation->fresh()->status)->toBe(MembershipStatus::Pending);
});

test('an invitation that was already answered cannot be answered again', function () {
    $user = User::factory()->create();
    $accepted = DocumentMembership::factory()->accepted()->create(['user_id' => $user->id]);
    $declined = DocumentMembership::factory()->declined()->create(['user_id' => $user->id]);

    $this->actingAs($user)->delete(route('invitations.destroy', $accepted))->assertNotFound();
    $this->actingAs($user)->patch(route('invitations.update', $declined))->assertNotFound();

    expect($accepted->fresh()->status)->toBe(MembershipStatus::Accepted)
        ->and($declined->fresh()->status)->toBe(MembershipStatus::Declined);
});

test('guests cannot reach the invitations', function () {
    $invitation = DocumentMembership::factory()->pending()->create();

    $this->get(route('invitations.index'))->assertRedirect(route('login'));
    $this->patch(route('invitations.update', $invitation))->assertRedirect(route('login'));
});
