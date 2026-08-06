<?php

use App\Broadcasting\DocumentChannel;
use App\Models\Document;
use App\Models\DocumentMembership;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

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
