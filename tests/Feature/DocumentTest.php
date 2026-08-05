<?php

use App\Enums\DocumentRole;
use App\Models\Document;
use App\Models\DocumentMembership;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Str;

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
