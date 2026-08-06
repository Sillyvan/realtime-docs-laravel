<?php

use App\Broadcasting\DocumentChannel;
use App\Events\DocumentAccessChanged;
use App\Events\DocumentTitleUpdated;
use App\Events\InvitationReceived;
use App\Models\Document;
use App\Models\DocumentMembership;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;

/**
 * The channels an event broadcasts on, named the way the client sees them.
 *
 * @return array<int, string>
 */
function broadcastChannels(object $event): array
{
    return array_map(fn (object $channel): string => (string) $channel, $event->broadcastOn());
}

test('the documents presence channel is wired to the channel class', function () {
    // `channel:list` prints through the console components, which the usual
    // expectsOutput* assertions do not capture — read the buffer directly.
    $this->withoutMockingConsoleOutput();

    expect($this->artisan('channel:list'))->toBe(0)
        ->and(Artisan::output())
        ->toContain('documents.{document}')
        ->toContain(DocumentChannel::class);
});

test('the channel resolves the document by the uuid the client puts in the channel name', function () {
    $document = Document::factory()->create();

    expect((new Document)->resolveRouteBinding($document->uuid)?->id)->toBe($document->id);
});

test('the owner joins as an editor', function () {
    $document = Document::factory()->create();

    expect((new DocumentChannel)->join($document->owner, $document))->toBe([
        'id' => $document->owner->id,
        'name' => $document->owner->name,
        'role' => 'editor',
    ]);
});

test('an accepted member joins with the role they were given', function (string $state, string $role) {
    $membership = DocumentMembership::factory()->accepted()->{$state}()->create();

    expect((new DocumentChannel)->join($membership->user, $membership->document))->toBe([
        'id' => $membership->user->id,
        'name' => $membership->user->name,
        'role' => $role,
    ]);
})->with([
    'editor' => ['editor', 'editor'],
    'viewer' => ['viewer', 'viewer'],
]);

test('a member who has not accepted the invitation is refused', function (string $state) {
    $membership = DocumentMembership::factory()->{$state}()->create();

    expect((new DocumentChannel)->join($membership->user, $membership->document))->toBeFalse();
})->with(['pending', 'declined']);

test('someone with no membership at all is refused', function () {
    $document = Document::factory()->create();

    expect((new DocumentChannel)->join(User::factory()->create(), $document))->toBeFalse();
});

test('renaming a document announces the new title on its channel', function () {
    Event::fake([DocumentTitleUpdated::class]);

    $document = Document::factory()->create(['title' => 'Draft']);

    $this->actingAs($document->owner)
        ->patch(route('documents.update', $document), ['title' => 'Roadmap'])
        ->assertRedirect();

    Event::assertDispatched(
        DocumentTitleUpdated::class,
        fn (DocumentTitleUpdated $event): bool => broadcastChannels($event) === ['presence-documents.'.$document->uuid]
            && $event->broadcastWith() === ['uuid' => $document->uuid, 'title' => 'Roadmap'],
    );
});

test('an update that leaves the title alone announces nothing', function () {
    Event::fake([DocumentTitleUpdated::class]);

    $document = Document::factory()->create(['title' => 'Draft']);
    $folder = $document->owner->folders()->create(['name' => 'Plans']);

    $this->actingAs($document->owner)->patch(route('documents.update', $document), [
        'title' => 'Draft',
        'folder' => $folder->uuid,
    ]);

    Event::assertNotDispatched(DocumentTitleUpdated::class);
});

test('an invitation reaches the invitee on their own private channel', function () {
    Event::fake([InvitationReceived::class]);

    $document = Document::factory()->create(['title' => 'Roadmap']);
    $invitee = User::factory()->create();

    $this->actingAs($document->owner)->post(route('documents.members.store', $document), [
        'email' => $invitee->email,
        'role' => 'viewer',
    ]);

    Event::assertDispatched(
        InvitationReceived::class,
        fn (InvitationReceived $event): bool => broadcastChannels($event) === ['private-App.Models.User.'.$invitee->id]
            && $event->broadcastWith() === [
                'document' => ['uuid' => $document->uuid, 'title' => 'Roadmap'],
                'role' => 'viewer',
                'invited_by' => $document->owner->name,
            ],
    );
});

test('a new role is announced on the document channel', function () {
    Event::fake([DocumentAccessChanged::class]);

    $membership = DocumentMembership::factory()->accepted()->viewer()->create();
    $document = $membership->document;

    $this->actingAs($document->owner)->patch(
        route('documents.members.update', [$document, $membership]),
        ['role' => 'editor'],
    );

    Event::assertDispatched(
        DocumentAccessChanged::class,
        fn (DocumentAccessChanged $event): bool => broadcastChannels($event) === ['presence-documents.'.$document->uuid]
            && $event->broadcastWith() === ['user_id' => $membership->user_id, 'role' => 'editor'],
    );
});

test('handing someone the role they already have announces nothing', function () {
    Event::fake([DocumentAccessChanged::class]);

    $membership = DocumentMembership::factory()->accepted()->editor()->create();

    $this->actingAs($membership->document->owner)->patch(
        route('documents.members.update', [$membership->document, $membership]),
        ['role' => 'editor'],
    );

    Event::assertNotDispatched(DocumentAccessChanged::class);
});

test('a revoked member is announced with no role at all', function () {
    Event::fake([DocumentAccessChanged::class]);

    $membership = DocumentMembership::factory()->accepted()->editor()->create();
    $document = $membership->document;

    $this->actingAs($document->owner)->delete(
        route('documents.members.destroy', [$document, $membership]),
    );

    Event::assertDispatched(
        DocumentAccessChanged::class,
        fn (DocumentAccessChanged $event): bool => broadcastChannels($event) === ['presence-documents.'.$document->uuid]
            && $event->broadcastWith() === ['user_id' => $membership->user_id, 'role' => null],
    );
});

test('leaving a document is announced the same way as being removed', function () {
    Event::fake([DocumentAccessChanged::class]);

    $membership = DocumentMembership::factory()->accepted()->editor()->create();

    $this->actingAs($membership->user)->delete(
        route('documents.members.destroy', [$membership->document, $membership]),
    );

    Event::assertDispatched(
        DocumentAccessChanged::class,
        fn (DocumentAccessChanged $event): bool => $event->broadcastWith() === [
            'user_id' => $membership->user_id,
            'role' => null,
        ],
    );
});
