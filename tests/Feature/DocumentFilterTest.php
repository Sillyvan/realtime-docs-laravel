<?php

use App\Models\Document;
use App\Models\DocumentMembership;
use App\Models\Folder;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Pull the titles the index rendered, in order.
 *
 * @param  array<string, mixed>  $query
 * @return array<int, string>
 */
function indexTitles(User $user, array $query = []): array
{
    $response = test()->actingAs($user)->get(route('documents.index', $query));

    $response->assertOk();

    return collect($response->viewData('page')['props']['documents']['data'])
        ->pluck('title')
        ->all();
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->folder = Folder::factory()->create(['user_id' => $this->user->id]);

    $this->filed = Document::factory()->inFolder($this->folder)->create([
        'title' => 'Filed roadmap',
        'content_text' => 'quarterly planning',
        'created_at' => now()->subDays(3),
        'updated_at' => now()->subDays(3),
    ]);

    $this->unfiled = Document::factory()->create([
        'owner_id' => $this->user->id,
        'title' => 'Unfiled notes',
        'content_text' => 'grocery list',
        'created_at' => now()->subDay(),
        'updated_at' => now()->subHour(),
    ]);

    $this->shared = Document::factory()->create([
        'title' => 'Shared brief',
        'content_text' => 'roadmap for the team',
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subMinutes(5),
    ]);

    DocumentMembership::factory()->accepted()->create([
        'document_id' => $this->shared->id,
        'user_id' => $this->user->id,
    ]);

    // Never visible to $this->user, whatever the filters say.
    Document::factory()->create(['title' => 'Someone elses roadmap']);
});

test('the default scope returns owned and shared documents, newest edit first', function () {
    expect(indexTitles($this->user))
        ->toBe(['Shared brief', 'Unfiled notes', 'Filed roadmap']);
});

test('scope mine excludes shared documents', function () {
    expect(indexTitles($this->user, ['scope' => 'mine']))
        ->toBe(['Unfiled notes', 'Filed roadmap']);
});

test('scope shared returns only documents shared with the user', function () {
    expect(indexTitles($this->user, ['scope' => 'shared']))
        ->toBe(['Shared brief']);
});

test('a pending or declined membership is not a shared document', function () {
    $pending = Document::factory()->create(['title' => 'Pending brief']);
    $declined = Document::factory()->create(['title' => 'Declined brief']);

    DocumentMembership::factory()->pending()->create([
        'document_id' => $pending->id,
        'user_id' => $this->user->id,
    ]);
    DocumentMembership::factory()->declined()->create([
        'document_id' => $declined->id,
        'user_id' => $this->user->id,
    ]);

    expect(indexTitles($this->user, ['scope' => 'shared']))->toBe(['Shared brief']);
});

test('folder filters by uuid and root filters to unfiled documents', function () {
    expect(indexTitles($this->user, ['folder' => $this->folder->uuid]))
        ->toBe(['Filed roadmap'])
        ->and(indexTitles($this->user, ['folder' => 'root']))
        ->toBe(['Shared brief', 'Unfiled notes']);
});

test('the folder filter is ignored for shared documents', function () {
    expect(indexTitles($this->user, ['scope' => 'shared', 'folder' => $this->folder->uuid]))
        ->toBe(['Shared brief']);
});

test('search matches the title and the body but never someone elses documents', function () {
    expect(indexTitles($this->user, ['q' => 'roadmap']))
        ->toBe(['Shared brief', 'Filed roadmap'])
        ->and(indexTitles($this->user, ['q' => 'grocery']))
        ->toBe(['Unfiled notes'])
        ->and(indexTitles($this->user, ['q' => 'nothing matches this']))
        ->toBe([]);
});

test('search combines with the other filters', function () {
    expect(indexTitles($this->user, ['q' => 'roadmap', 'scope' => 'mine']))
        ->toBe(['Filed roadmap']);
});

test('every sort and direction pair orders the list', function () {
    expect(indexTitles($this->user, ['sort' => 'updated_at', 'direction' => 'asc']))
        ->toBe(['Filed roadmap', 'Unfiled notes', 'Shared brief'])
        ->and(indexTitles($this->user, ['sort' => 'created_at', 'direction' => 'desc']))
        ->toBe(['Unfiled notes', 'Shared brief', 'Filed roadmap'])
        ->and(indexTitles($this->user, ['sort' => 'created_at', 'direction' => 'asc']))
        ->toBe(['Filed roadmap', 'Shared brief', 'Unfiled notes'])
        ->and(indexTitles($this->user, ['sort' => 'title', 'direction' => 'asc']))
        ->toBe(['Filed roadmap', 'Shared brief', 'Unfiled notes'])
        ->and(indexTitles($this->user, ['sort' => 'title', 'direction' => 'desc']))
        ->toBe(['Unfiled notes', 'Shared brief', 'Filed roadmap']);
});

test('the filters are echoed back to the page with their defaults resolved', function () {
    $this->actingAs($this->user)
        ->get(route('documents.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.scope', 'all')
            ->where('filters.folder', null)
            ->where('filters.q', null)
            ->where('filters.sort', 'updated_at')
            ->where('filters.direction', 'desc')
        );

    $this->actingAs($this->user)
        ->get(route('documents.index', ['scope' => 'shared', 'folder' => $this->folder->uuid]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.scope', 'shared')
            ->where('filters.folder', null)
        );
});

test('the partial reload the filter controls use returns the new filters alongside the list', function () {
    // The version has to match the middleware's or Inertia answers 409 instead.
    $version = $this->actingAs($this->user)
        ->get(route('documents.index'))
        ->viewData('page')['version'];

    $this->actingAs($this->user)
        ->get(route('documents.index', ['scope' => 'mine', 'folder' => $this->folder->uuid]), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => 'documents/index',
            'X-Inertia-Partial-Data' => 'documents,filters',
        ])
        ->assertOk()
        ->assertJsonPath('props.filters.scope', 'mine')
        ->assertJsonPath('props.filters.folder', $this->folder->uuid)
        ->assertJsonPath('props.documents.data.0.title', 'Filed roadmap')
        ->assertJsonMissingPath('props.folders');
});

test('invalid filter values are rejected rather than silently dropped', function () {
    $cases = [
        ['scope' => 'everything'],
        ['sort' => 'content_text'],
        ['direction' => 'sideways'],
        ['folder' => 'not-a-folder'],
        ['page' => 0],
    ];

    foreach ($cases as $query) {
        $this->actingAs($this->user)
            ->from(route('documents.index'))
            ->get(route('documents.index', $query))
            ->assertSessionHasErrors(array_key_first($query));
    }
});

test('a folder belonging to someone else cannot be used as a filter', function () {
    $foreign = Folder::factory()->create();

    $this->actingAs($this->user)
        ->from(route('documents.index'))
        ->get(route('documents.index', ['folder' => $foreign->uuid]))
        ->assertSessionHasErrors('folder');
});

test('the list is paginated twenty at a time and keeps the filters in its links', function () {
    Document::factory()->count(21)->create([
        'owner_id' => $this->user->id,
        'title' => 'Bulk',
    ]);

    $this->actingAs($this->user)
        ->get(route('documents.index', ['scope' => 'mine', 'sort' => 'title']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('documents.data', 20)
            ->where('documents.total', 23)
            ->where('documents.current_page', 1)
            ->where('documents.last_page', 2)
        );

    $this->actingAs($this->user)
        ->get(route('documents.index', ['scope' => 'mine', 'sort' => 'title', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('documents.data', 3)
            ->where('documents.current_page', 2)
            ->where('documents.next_page_url', null)
        );
});
