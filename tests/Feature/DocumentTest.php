<?php

use App\Enums\DocumentRole;
use App\Models\Document;
use App\Models\DocumentMembership;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('a document gets a uuid on create while keeping an incrementing id', function () {
    $document = Document::factory()->create();

    expect($document->uuid)->toBeString()
        ->and(Str::isUuid($document->uuid))->toBeTrue()
        ->and($document->id)->toBeInt()
        ->and($document->getRouteKeyName())->toBe('uuid');
});

test('the owner is always an editor', function () {
    $document = Document::factory()->create();

    expect($document->roleFor($document->owner))->toBe(DocumentRole::Editor);
});

test('roleFor returns the role of an accepted member', function () {
    $document = Document::factory()->create();
    $member = User::factory()->create();

    DocumentMembership::factory()->accepted()->editor()->create([
        'document_id' => $document->id,
        'user_id' => $member->id,
    ]);

    expect($document->roleFor($member))->toBe(DocumentRole::Editor);
});

test('roleFor returns null for pending, declined and unrelated users', function () {
    $document = Document::factory()->create();
    $pending = User::factory()->create();
    $declined = User::factory()->create();
    $stranger = User::factory()->create();

    DocumentMembership::factory()->pending()->create([
        'document_id' => $document->id,
        'user_id' => $pending->id,
    ]);
    DocumentMembership::factory()->declined()->create([
        'document_id' => $document->id,
        'user_id' => $declined->id,
    ]);

    expect($document->roleFor($pending))->toBeNull()
        ->and($document->roleFor($declined))->toBeNull()
        ->and($document->roleFor($stranger))->toBeNull();
});

test('scopes separate owned, shared and unrelated documents', function () {
    $user = User::factory()->create();
    $owned = Document::factory()->create(['owner_id' => $user->id]);
    $shared = Document::factory()->create();
    $pending = Document::factory()->create();
    $other = Document::factory()->create();

    DocumentMembership::factory()->accepted()->create([
        'document_id' => $shared->id,
        'user_id' => $user->id,
    ]);
    DocumentMembership::factory()->pending()->create([
        'document_id' => $pending->id,
        'user_id' => $user->id,
    ]);

    expect(Document::ownedBy($user)->pluck('id')->all())->toBe([$owned->id])
        ->and(Document::sharedWith($user)->pluck('id')->all())->toBe([$shared->id])
        ->and(Document::forUser($user)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$owned->id, $shared->id])->sort()->values()->all())
        ->and(Document::forUser($user)->pluck('id'))->not->toContain($other->id);
});

test('inFolder filters by folder uuid and by root', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id]);
    $filed = Document::factory()->inFolder($folder)->create();
    $root = Document::factory()->create(['owner_id' => $user->id]);

    expect(Document::inFolder($folder->uuid)->pluck('id')->all())->toBe([$filed->id])
        ->and(Document::ownedBy($user)->inFolder('root')->pluck('id')->all())->toBe([$root->id])
        ->and(Document::ownedBy($user)->inFolder(null)->count())->toBe(2);
});

test('search matches title and body', function () {
    $byTitle = Document::factory()->create(['title' => 'Quarterly roadmap']);
    $byBody = Document::factory()->create(['content_text' => 'notes about the roadmap']);
    Document::factory()->create(['title' => 'Grocery list', 'content_text' => 'milk']);

    expect(Document::search('roadmap')->pluck('id')->sort()->values()->all())
        ->toBe(collect([$byTitle->id, $byBody->id])->sort()->values()->all())
        ->and(Document::search(null)->count())->toBe(3);
});

test('user relations return owned, shared and pending records', function () {
    $user = User::factory()->create();
    $owned = Document::factory()->create(['owner_id' => $user->id]);
    $shared = Document::factory()->create();
    $invited = Document::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id]);

    DocumentMembership::factory()->accepted()->create([
        'document_id' => $shared->id,
        'user_id' => $user->id,
    ]);
    DocumentMembership::factory()->pending()->create([
        'document_id' => $invited->id,
        'user_id' => $user->id,
    ]);

    expect($user->documents->pluck('id')->all())->toBe([$owned->id])
        ->and($user->folders->pluck('id')->all())->toBe([$folder->id])
        ->and($user->sharedDocuments->pluck('id')->all())->toBe([$shared->id])
        ->and($user->pendingInvitations->pluck('document_id')->all())->toBe([$invited->id]);
});

test('deleting a folder keeps its documents at the root', function () {
    $folder = Folder::factory()->create();
    $document = Document::factory()->inFolder($folder)->create();

    $folder->delete();

    expect($document->fresh()->folder_id)->toBeNull();
});

test('the document policy follows ownership and membership', function () {
    $document = Document::factory()->create();
    $owner = $document->owner;
    $editor = User::factory()->create();
    $viewer = User::factory()->create();
    $stranger = User::factory()->create();

    DocumentMembership::factory()->accepted()->editor()->create([
        'document_id' => $document->id,
        'user_id' => $editor->id,
    ]);
    DocumentMembership::factory()->accepted()->viewer()->create([
        'document_id' => $document->id,
        'user_id' => $viewer->id,
    ]);

    expect($owner->can('update', $document))->toBeTrue()
        ->and($owner->can('delete', $document))->toBeTrue()
        ->and($owner->can('manageMembers', $document))->toBeTrue()
        ->and($owner->can('file', $document))->toBeTrue()
        ->and($editor->can('view', $document))->toBeTrue()
        ->and($editor->can('update', $document))->toBeTrue()
        ->and($editor->can('delete', $document))->toBeFalse()
        ->and($editor->can('manageMembers', $document))->toBeFalse()
        ->and($editor->can('file', $document))->toBeFalse()
        ->and($viewer->can('view', $document))->toBeTrue()
        ->and($viewer->can('update', $document))->toBeFalse()
        ->and($stranger->can('view', $document))->toBeFalse();
});

test('the folder policy is owner only', function () {
    $folder = Folder::factory()->create();
    $stranger = User::factory()->create();

    expect($folder->user->can('update', $folder))->toBeTrue()
        ->and($folder->user->can('delete', $folder))->toBeTrue()
        ->and($stranger->can('view', $folder))->toBeFalse()
        ->and($stranger->can('update', $folder))->toBeFalse()
        ->and($stranger->can('delete', $folder))->toBeFalse();
});

test('guests cannot reach documents', function () {
    $this->get(route('documents.index'))->assertRedirect(route('login'));
});

test('a user can create an empty document and lands on it', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('documents.store'));

    $document = Document::sole();

    expect($document->owner_id)->toBe($user->id)
        ->and($document->title)->toBe('Untitled document');

    $response->assertRedirect(route('documents.show', $document));
});

test('the index lists documents the user owns or shares, and nothing else', function () {
    $user = User::factory()->create();
    $owned = Document::factory()->create([
        'owner_id' => $user->id,
        'title' => 'Mine',
        'updated_at' => now()->subHour(),
    ]);
    $shared = Document::factory()->create(['title' => 'Shared']);
    Document::factory()->create(['title' => 'Someone elses']);

    DocumentMembership::factory()->accepted()->create([
        'document_id' => $shared->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('documents.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/index')
            ->has('documents.data', 2)
            ->where('documents.total', 2)
            ->where('documents.data.0.title', 'Shared')
            ->where('documents.data.0.is_owner', false)
            ->where('documents.data.0.role', 'viewer')
            ->where('documents.data.1.title', 'Mine')
            ->where('documents.data.1.is_owner', true)
        );

    expect($owned->fresh())->not->toBeNull();
});

test('the owner can open a document with edit permissions', function () {
    $document = Document::factory()->create(['content_html' => '<p>Hello</p>']);

    $this->actingAs($document->owner)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/show')
            ->where('document.uuid', $document->uuid)
            ->where('document.content_html', '<p>Hello</p>')
            ->where('can.update', true)
            ->where('can.delete', true)
        );
});

test('a viewer opens a document read only', function () {
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
            ->where('can.delete', false)
        );
});

test('a non member cannot open a document', function () {
    $document = Document::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('documents.show', $document))
        ->assertForbidden();
});

test('an unknown uuid is a 404', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('documents.show', 'not-a-uuid'))
        ->assertNotFound();
});

test('the owner can rename a document', function () {
    $document = Document::factory()->create();

    $this->actingAs($document->owner)
        ->from(route('documents.show', $document))
        ->patch(route('documents.update', $document), ['title' => 'Roadmap'])
        ->assertRedirect(route('documents.show', $document));

    expect($document->fresh()->title)->toBe('Roadmap');
});

test('renaming requires a title', function () {
    $document = Document::factory()->create();

    $this->actingAs($document->owner)
        ->from(route('documents.show', $document))
        ->patch(route('documents.update', $document), ['title' => ''])
        ->assertSessionHasErrors('title');
});

test('a viewer cannot rename a document', function () {
    $document = Document::factory()->create();
    $viewer = User::factory()->create();

    DocumentMembership::factory()->accepted()->viewer()->create([
        'document_id' => $document->id,
        'user_id' => $viewer->id,
    ]);

    $this->actingAs($viewer)
        ->patch(route('documents.update', $document), ['title' => 'Nope'])
        ->assertForbidden();

    expect($document->fresh()->title)->not->toBe('Nope');
});

test('autosave persists the yjs state alongside the html and text snapshot', function () {
    $document = Document::factory()->create();
    // A Yjs update is arbitrary binary — null bytes, invalid UTF-8, the lot.
    $state = "\x00\x01\xff\xfe".random_bytes(64)."\x00";

    $this->actingAs($document->owner)
        ->put(route('documents.content.update', $document), [
            'state' => base64_encode($state),
            'content_html' => '<p>Typed live</p>',
            'content_text' => 'Typed live',
        ])
        ->assertNoContent();

    expect($document->fresh())
        ->state->toBe($state)
        ->content_html->toBe('<p>Typed live</p>')
        ->content_text->toBe('Typed live');
});

test('the state round trips back to the editor as base64', function () {
    $state = random_bytes(96);
    $document = Document::factory()->create(['state' => $state]);

    $this->actingAs($document->owner)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('document.state', base64_encode($state))
        );
});

test('a document nobody has typed in yet has no state', function () {
    $document = Document::factory()->create(['state' => null]);

    $this->actingAs($document->owner)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('document.state', null));
});

test('autosave rejects a state that is not base64', function () {
    $document = Document::factory()->create();

    $this->actingAs($document->owner)
        ->putJson(route('documents.content.update', $document), [
            'state' => 'not base64 !!',
            'content_html' => '<p>Nope</p>',
            'content_text' => 'Nope',
        ])
        ->assertJsonValidationErrors('state');

    expect($document->fresh()->state)->toBeNull();
});

test('an editor can autosave but a viewer cannot', function () {
    $document = Document::factory()->create();
    $editor = User::factory()->create();
    $viewer = User::factory()->create();

    DocumentMembership::factory()->accepted()->editor()->create([
        'document_id' => $document->id,
        'user_id' => $editor->id,
    ]);
    DocumentMembership::factory()->accepted()->viewer()->create([
        'document_id' => $document->id,
        'user_id' => $viewer->id,
    ]);

    $payload = [
        'state' => base64_encode(random_bytes(32)),
        'content_html' => '<p>Edit</p>',
        'content_text' => 'Edit',
    ];

    $this->actingAs($editor)
        ->put(route('documents.content.update', $document), $payload)
        ->assertNoContent();

    $this->actingAs($viewer)
        ->put(route('documents.content.update', $document), $payload)
        ->assertForbidden();
});

test('the owner can delete a document and a member cannot', function () {
    $document = Document::factory()->create();
    $editor = User::factory()->create();

    DocumentMembership::factory()->accepted()->editor()->create([
        'document_id' => $document->id,
        'user_id' => $editor->id,
    ]);

    $this->actingAs($editor)
        ->delete(route('documents.destroy', $document))
        ->assertForbidden();

    $this->actingAs($document->owner)
        ->delete(route('documents.destroy', $document))
        ->assertRedirect(route('documents.index'));

    expect(Document::count())->toBe(0);
});
