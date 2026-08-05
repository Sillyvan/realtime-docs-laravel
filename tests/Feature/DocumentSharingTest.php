<?php

use App\Enums\DocumentRole;
use App\Enums\MembershipStatus;
use App\Models\Document;
use App\Models\DocumentMembership;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the owner invites an account by email and gets a pending membership', function () {
    $document = Document::factory()->create();
    $invitee = User::factory()->create();

    $response = $this->actingAs($document->owner)->post(
        route('documents.members.store', $document),
        ['email' => $invitee->email, 'role' => 'editor'],
    );

    $response->assertRedirect();

    $membership = DocumentMembership::sole();

    expect($membership->document_id)->toBe($document->id)
        ->and($membership->user_id)->toBe($invitee->id)
        ->and($membership->invited_by_id)->toBe($document->owner_id)
        ->and($membership->role)->toBe(DocumentRole::Editor)
        ->and($membership->status)->toBe(MembershipStatus::Pending)
        ->and($membership->responded_at)->toBeNull();
});

test('inviting an email without an account fails validation', function () {
    $document = Document::factory()->create();

    $this->actingAs($document->owner)
        ->post(route('documents.members.store', $document), [
            'email' => 'nobody@example.com',
            'role' => 'viewer',
        ])
        ->assertSessionHasErrors(['email' => 'No account with that email.']);

    expect(DocumentMembership::count())->toBe(0);
});

test('the owner cannot invite themselves', function () {
    $document = Document::factory()->create();

    $this->actingAs($document->owner)
        ->post(route('documents.members.store', $document), [
            'email' => $document->owner->email,
            'role' => 'editor',
        ])
        ->assertSessionHasErrors(['email' => 'They already own this document.']);

    expect(DocumentMembership::count())->toBe(0);
});

test('an invalid role is rejected', function () {
    $document = Document::factory()->create();
    $invitee = User::factory()->create();

    $this->actingAs($document->owner)
        ->post(route('documents.members.store', $document), [
            'email' => $invitee->email,
            'role' => 'owner',
        ])
        ->assertSessionHasErrors('role');
});

test('a declined invitation can be sent again on the same row', function () {
    $document = Document::factory()->create();
    $invitee = User::factory()->create();
    $membership = DocumentMembership::factory()->declined()->viewer()->create([
        'document_id' => $document->id,
        'user_id' => $invitee->id,
    ]);

    $this->actingAs($document->owner)
        ->post(route('documents.members.store', $document), [
            'email' => $invitee->email,
            'role' => 'editor',
        ])
        ->assertSessionHasNoErrors();

    expect(DocumentMembership::count())->toBe(1)
        ->and($membership->fresh())
        ->status->toBe(MembershipStatus::Pending)
        ->role->toBe(DocumentRole::Editor)
        ->responded_at->toBeNull();
});

test('an accepted member cannot be invited again', function () {
    $document = Document::factory()->create();
    $member = User::factory()->create();
    DocumentMembership::factory()->accepted()->viewer()->create([
        'document_id' => $document->id,
        'user_id' => $member->id,
    ]);

    $this->actingAs($document->owner)
        ->post(route('documents.members.store', $document), [
            'email' => $member->email,
            'role' => 'editor',
        ])
        ->assertSessionHasErrors(['email' => 'They already have access to this document.']);

    expect(DocumentMembership::sole()->role)->toBe(DocumentRole::Viewer);
});

test('only the owner can invite', function () {
    $document = Document::factory()->create();
    $editor = User::factory()->create();
    $stranger = User::factory()->create();
    DocumentMembership::factory()->accepted()->editor()->create([
        'document_id' => $document->id,
        'user_id' => $editor->id,
    ]);

    $payload = ['email' => User::factory()->create()->email, 'role' => 'viewer'];

    $this->actingAs($editor)
        ->post(route('documents.members.store', $document), $payload)
        ->assertForbidden();

    $this->actingAs($stranger)
        ->post(route('documents.members.store', $document), $payload)
        ->assertForbidden();

    expect(DocumentMembership::count())->toBe(1);
});

test('a viewer can open the document but not write to it', function () {
    $document = Document::factory()->create();
    $viewer = User::factory()->create();
    DocumentMembership::factory()->accepted()->viewer()->create([
        'document_id' => $document->id,
        'user_id' => $viewer->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.update', false)
            ->where('can.manageMembers', false),
        );

    $this->actingAs($viewer)
        ->put(route('documents.content.update', $document), [
            'content_html' => '<p>sneaky</p>',
            'content_text' => 'sneaky',
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->patch(route('documents.update', $document), ['title' => 'Sneaky'])
        ->assertForbidden();

    expect($document->fresh()->content_text)->not->toBe('sneaky');
});

test('an editor can open and write to the document', function () {
    $document = Document::factory()->create();
    $editor = User::factory()->create();
    DocumentMembership::factory()->accepted()->editor()->create([
        'document_id' => $document->id,
        'user_id' => $editor->id,
    ]);

    $this->actingAs($editor)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.update', true)
            ->where('can.manageMembers', false)
            ->where('can.delete', false),
        );

    $this->actingAs($editor)
        ->put(route('documents.content.update', $document), [
            'content_html' => '<p>edited</p>',
            'content_text' => 'edited',
        ])
        ->assertNoContent();

    expect($document->fresh()->content_text)->toBe('edited');
});

test('a pending or declined invitation grants no access', function () {
    $document = Document::factory()->create();
    $pending = User::factory()->create();
    $declined = User::factory()->create();

    DocumentMembership::factory()->pending()->editor()->create([
        'document_id' => $document->id,
        'user_id' => $pending->id,
    ]);
    DocumentMembership::factory()->declined()->editor()->create([
        'document_id' => $document->id,
        'user_id' => $declined->id,
    ]);

    $this->actingAs($pending)->get(route('documents.show', $document))->assertForbidden();
    $this->actingAs($declined)->get(route('documents.show', $document))->assertForbidden();
});

test('the owner sees every membership with emails, a member sees only accepted ones', function () {
    $document = Document::factory()->create();
    $accepted = User::factory()->create(['name' => 'Ada']);
    $pending = User::factory()->create(['name' => 'Grace']);

    DocumentMembership::factory()->accepted()->viewer()->create([
        'document_id' => $document->id,
        'user_id' => $accepted->id,
    ]);
    DocumentMembership::factory()->pending()->editor()->create([
        'document_id' => $document->id,
        'user_id' => $pending->id,
    ]);

    $this->actingAs($document->owner)
        ->get(route('documents.show', $document))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageMembers', true)
            ->has('members', 3)
            ->where('members.0.id', null)
            ->where('members.0.name', $document->owner->name)
            ->where('members.0.email', $document->owner->email)
            ->where('members.0.is_you', true)
            ->where('members.1.name', 'Ada')
            ->where('members.1.status', 'accepted')
            ->where('members.2.name', 'Grace')
            ->where('members.2.status', 'pending'),
        );

    $this->actingAs($accepted)
        ->get(route('documents.show', $document))
        ->assertInertia(fn (Assert $page) => $page
            ->has('members', 2)
            ->where('members.0.email', null)
            ->where('members.1.name', 'Ada')
            ->where('members.1.is_you', true)
            ->where('members.1.email', null),
        );
});

test('the owner changes a role and the member gains or loses editing', function () {
    $document = Document::factory()->create();
    $member = User::factory()->create();
    $membership = DocumentMembership::factory()->accepted()->viewer()->create([
        'document_id' => $document->id,
        'user_id' => $member->id,
    ]);

    $this->actingAs($document->owner)
        ->patch(route('documents.members.update', [$document, $membership]), ['role' => 'editor'])
        ->assertRedirect();

    expect($membership->fresh()->role)->toBe(DocumentRole::Editor)
        ->and($member->can('update', $document->fresh()))->toBeTrue();
});

test('nobody but the owner can change a role', function () {
    $document = Document::factory()->create();
    $editor = User::factory()->create();
    $membership = DocumentMembership::factory()->accepted()->editor()->create([
        'document_id' => $document->id,
        'user_id' => $editor->id,
    ]);

    $this->actingAs($editor)
        ->patch(route('documents.members.update', [$document, $membership]), ['role' => 'viewer'])
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->patch(route('documents.members.update', [$document, $membership]), ['role' => 'viewer'])
        ->assertForbidden();

    expect($membership->fresh()->role)->toBe(DocumentRole::Editor);
});

test('the owner revokes access', function () {
    $document = Document::factory()->create();
    $member = User::factory()->create();
    $membership = DocumentMembership::factory()->accepted()->editor()->create([
        'document_id' => $document->id,
        'user_id' => $member->id,
    ]);

    $this->actingAs($document->owner)
        ->delete(route('documents.members.destroy', [$document, $membership]))
        ->assertRedirect();

    expect(DocumentMembership::count())->toBe(0);

    $this->actingAs($member)->get(route('documents.show', $document))->assertForbidden();
});

test('a member leaves a document and is sent back to the index', function () {
    $document = Document::factory()->create();
    $member = User::factory()->create();
    $membership = DocumentMembership::factory()->accepted()->editor()->create([
        'document_id' => $document->id,
        'user_id' => $member->id,
    ]);

    $this->actingAs($member)
        ->delete(route('documents.members.destroy', [$document, $membership]))
        ->assertRedirect(route('documents.index'));

    expect(DocumentMembership::count())->toBe(0);
});

test('a member cannot revoke somebody else', function () {
    $document = Document::factory()->create();
    $editor = User::factory()->create();
    $other = User::factory()->create();
    DocumentMembership::factory()->accepted()->editor()->create([
        'document_id' => $document->id,
        'user_id' => $editor->id,
    ]);
    $membership = DocumentMembership::factory()->accepted()->viewer()->create([
        'document_id' => $document->id,
        'user_id' => $other->id,
    ]);

    $this->actingAs($editor)
        ->delete(route('documents.members.destroy', [$document, $membership]))
        ->assertForbidden();

    expect(DocumentMembership::count())->toBe(2);
});

test('a membership of another document cannot be reached through this one', function () {
    $document = Document::factory()->create();
    $otherDocument = Document::factory()->create(['owner_id' => $document->owner_id]);
    $membership = DocumentMembership::factory()->accepted()->create([
        'document_id' => $otherDocument->id,
    ]);

    $this->actingAs($document->owner)
        ->patch(route('documents.members.update', [$document, $membership]), ['role' => 'viewer'])
        ->assertNotFound();
});

test('the index counts who a document is shared with', function () {
    $user = User::factory()->create();
    $document = Document::factory()->create(['owner_id' => $user->id]);

    DocumentMembership::factory()->accepted()->count(2)->create(['document_id' => $document->id]);
    DocumentMembership::factory()->pending()->create(['document_id' => $document->id]);

    $this->actingAs($user)
        ->get(route('documents.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('documents.data.0.shared_with_count', 2),
        );
});
