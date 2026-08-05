<?php

namespace App\Http\Controllers;

use App\Http\Requests\Documents\DocumentIndexRequest;
use App\Http\Requests\Documents\DocumentUpdateRequest;
use App\Models\Document;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    /**
     * List the documents the user owns or has been given access to.
     */
    public function index(DocumentIndexRequest $request): Response
    {
        $user = $request->user();
        $filters = $request->filters();

        return Inertia::render('documents/index', [
            'documents' => fn (): LengthAwarePaginator => $this->paginateDocuments($user, $filters),
            'folders' => fn (): Collection => $this->foldersFor($user),
            'filters' => $filters,
        ]);
    }

    /**
     * Create an empty document and open it.
     */
    public function store(Request $request): RedirectResponse
    {
        $document = $request->user()->documents()->create([]);

        return to_route('documents.show', $document);
    }

    /**
     * Show the document in the editor.
     */
    public function show(Request $request, Document $document): Response
    {
        Gate::authorize('view', $document);

        $user = $request->user();
        $canFile = $user->can('file', $document);

        return Inertia::render('documents/show', [
            'document' => [
                'uuid' => $document->uuid,
                'title' => $document->title,
                'content_html' => $document->content_html ?? '',
                'updated_at_for_humans' => $document->updated_at?->diffForHumans(),
                'folder' => $this->toFolderRef($document),
            ],
            'folders' => fn (): Collection => $canFile ? $this->foldersFor($user) : collect(),
            'can' => [
                'update' => $user->can('update', $document),
                'delete' => $user->can('delete', $document),
                'file' => $canFile,
            ],
        ]);
    }

    /**
     * Rename the document or file it in a folder.
     */
    public function update(DocumentUpdateRequest $request, Document $document): RedirectResponse
    {
        $document->update($request->documentAttributes());

        return back();
    }

    /**
     * Delete the document.
     */
    public function destroy(Document $document): RedirectResponse
    {
        Gate::authorize('delete', $document);

        $document->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document deleted.')]);

        return to_route('documents.index');
    }

    /**
     * Run the filtered, sorted, paginated index query.
     *
     * @param  array{scope: string, folder: string|null, q: string|null, sort: string, direction: string}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginateDocuments(User $user, array $filters): LengthAwarePaginator
    {
        $query = Document::query();

        match ($filters['scope']) {
            'mine' => $query->ownedBy($user),
            'shared' => $query->sharedWith($user),
            default => $query->forUser($user),
        };

        return $query
            ->inFolder($filters['folder'])
            ->search($filters['q'])
            ->with([
                'owner:id,name',
                'folder:id,uuid,name',
                'memberships' => fn (Relation $memberships) => $memberships->where('user_id', $user->id),
            ])
            ->orderBy($filters['sort'], $filters['direction'])
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Document $document): array => $this->toListItem($document, $user));
    }

    /**
     * The user's folders, with how many documents each one holds.
     *
     * @return Collection<int, array{uuid: string, name: string, documents_count: int}>
     */
    private function foldersFor(User $user): Collection
    {
        return $user->folders()
            ->withCount('documents')
            ->orderBy('name')
            ->get()
            ->map(fn (Folder $folder): array => [
                'uuid' => $folder->uuid,
                'name' => $folder->name,
                'documents_count' => (int) $folder->documents_count,
            ]);
    }

    /**
     * Shape a document for the index list.
     *
     * @return array{uuid: string, title: string, excerpt: string, updated_at_for_humans: string|null, owner: array{name: string}, is_owner: bool, role: string|null, folder: array{uuid: string, name: string}|null}
     */
    private function toListItem(Document $document, User $user): array
    {
        return [
            'uuid' => $document->uuid,
            'title' => $document->title,
            'excerpt' => Str::limit((string) $document->content_text, 140),
            'updated_at_for_humans' => $document->updated_at?->diffForHumans(),
            'owner' => ['name' => $document->owner->name],
            'is_owner' => $document->owner_id === $user->id,
            'role' => $document->roleFor($user)?->value,
            'folder' => $this->toFolderRef($document),
        ];
    }

    /**
     * Shape the folder a document is filed in, if any.
     *
     * @return array{uuid: string, name: string}|null
     */
    private function toFolderRef(Document $document): ?array
    {
        $folder = $document->folder;

        return $folder === null
            ? null
            : ['uuid' => $folder->uuid, 'name' => $folder->name];
    }
}
