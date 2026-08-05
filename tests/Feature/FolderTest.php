<?php

use App\Models\Document;
use App\Models\DocumentMembership;
use App\Models\Folder;
use App\Models\User;

test('a user can create a folder and lands on it', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('folders.store'), ['name' => 'Roadmaps']);

    $folder = Folder::sole();

    expect($folder->user_id)->toBe($user->id)
        ->and($folder->name)->toBe('Roadmaps');
});

test('a folder needs a name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('documents.index'))
        ->post(route('folders.store'), ['name' => ''])
        ->assertSessionHasErrors('name');

    expect(Folder::count())->toBe(0);
});

test('folder names are unique per user but not across users', function () {
    $user = User::factory()->create();
    Folder::factory()->create(['user_id' => $user->id, 'name' => 'Roadmaps']);

    $this->actingAs($user)
        ->from(route('documents.index'))
        ->post(route('folders.store'), ['name' => 'Roadmaps'])
        ->assertSessionHasErrors('name');

    $this->actingAs(User::factory()->create())
        ->post(route('folders.store'), ['name' => 'Roadmaps']);

    expect(Folder::where('name', 'Roadmaps')->count())->toBe(2);
});

test('a user can rename their folder, keeping its own name available', function () {
    $folder = Folder::factory()->create(['name' => 'Drafts']);

    $this->actingAs($folder->user)
        ->from(route('documents.index'))
        ->patch(route('folders.update', $folder), ['name' => 'Drafts'])
        ->assertSessionHasNoErrors();

    $this->actingAs($folder->user)
        ->patch(route('folders.update', $folder), ['name' => 'Archive']);

    expect($folder->fresh()->name)->toBe('Archive');
});

test('a user cannot rename or delete someone elses folder', function () {
    $folder = Folder::factory()->create(['name' => 'Drafts']);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->patch(route('folders.update', $folder), ['name' => 'Mine now'])
        ->assertForbidden();

    $this->actingAs($stranger)
        ->delete(route('folders.destroy', $folder))
        ->assertForbidden();

    expect($folder->fresh()->name)->toBe('Drafts');
});

test('deleting a folder keeps its documents and unfiles them', function () {
    $folder = Folder::factory()->create();
    $document = Document::factory()->inFolder($folder)->create();

    $this->actingAs($folder->user)
        ->delete(route('folders.destroy', $folder))
        ->assertRedirect(route('documents.index'));

    expect(Folder::count())->toBe(0)
        ->and($document->fresh())->not->toBeNull()
        ->and($document->fresh()->folder_id)->toBeNull();
});

test('the owner can file a document into their own folder and back to the root', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id]);
    $document = Document::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)
        ->from(route('documents.show', $document))
        ->patch(route('documents.update', $document), ['folder' => $folder->uuid]);

    expect($document->fresh()->folder_id)->toBe($folder->id);

    $this->actingAs($user)
        ->from(route('documents.show', $document))
        ->patch(route('documents.update', $document), ['folder' => null]);

    expect($document->fresh()->folder_id)->toBeNull();
});

test('a document cannot be filed into someone elses folder', function () {
    $document = Document::factory()->create();
    $foreign = Folder::factory()->create();

    $this->actingAs($document->owner)
        ->from(route('documents.show', $document))
        ->patch(route('documents.update', $document), ['folder' => $foreign->uuid])
        ->assertSessionHasErrors('folder');

    expect($document->fresh()->folder_id)->toBeNull();
});

test('an editor can rename a document but cannot file it', function () {
    $document = Document::factory()->create();
    $editor = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $editor->id]);

    DocumentMembership::factory()->accepted()->editor()->create([
        'document_id' => $document->id,
        'user_id' => $editor->id,
    ]);

    $this->actingAs($editor)
        ->from(route('documents.show', $document))
        ->patch(route('documents.update', $document), ['title' => 'Renamed'])
        ->assertSessionHasNoErrors();

    $this->actingAs($editor)
        ->patch(route('documents.update', $document), ['folder' => $folder->uuid])
        ->assertForbidden();

    expect($document->fresh())
        ->title->toBe('Renamed')
        ->folder_id->toBeNull();
});

test('the index exposes the users folders with their document counts', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id, 'name' => 'Roadmaps']);
    Document::factory()->count(2)->inFolder($folder)->create();
    Folder::factory()->create(['name' => 'Someone elses']);

    $this->actingAs($user)
        ->get(route('documents.index'))
        ->assertInertia(fn ($page) => $page
            ->has('folders', 1)
            ->where('folders.0.name', 'Roadmaps')
            ->where('folders.0.uuid', $folder->uuid)
            ->where('folders.0.documents_count', 2)
        );
});
