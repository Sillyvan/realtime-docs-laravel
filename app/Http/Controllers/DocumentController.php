<?php

namespace App\Http\Controllers;

use App\Enums\DocumentRole;
use App\Enums\MembershipStatus;
use App\Http\Requests\Documents\DocumentIndexRequest;
use App\Http\Requests\Documents\DocumentUpdateRequest;
use App\Models\Document;
use App\Models\DocumentMembership;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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
        $canManageMembers = $user->can('manageMembers', $document);

        return Inertia::render('documents/show', [
            'document' => [
                'uuid' => $document->uuid,
                'title' => $document->title,
                'content_html' => $document->content_html ?? '',
                // The CRDT the editor rebuilds itself from; base64 because it is binary.
                'state' => $document->state === null ? null : base64_encode($document->state),
                'updated_at_for_humans' => $document->updated_at?->diffForHumans(),
                'folder' => $this->toFolderRef($document),
            ],
            'members' => fn (): Collection => $this->membersFor($document, $user, $canManageMembers),
            'folders' => fn (): Collection => $canFile ? $this->foldersFor($user) : collect(),
            'can' => [
                'update' => $user->can('update', $document),
                'delete' => $user->can('delete', $document),
                'file' => $canFile,
                'manageMembers' => $canManageMembers,
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
            ->withCount([
                'memberships as shared_with_count' => fn (Builder $memberships) => $memberships
                    ->where('status', MembershipStatus::Accepted),
            ])
            ->orderBy($filters['sort'], $filters['direction'])
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Document $document): array => $this->toListItem($document, $user));
    }

    /**
     * The people on the document: the owner first, then the membership rows.
     *
     * Only someone who can manage members sees email addresses, and the invitations
     * that are still pending or were declined.
     *
     * @return Collection<int, array{id: int|null, name: string, email: string|null, role: string, status: string, is_you: bool}>
     */
    private function membersFor(Document $document, User $user, bool $manages): Collection
    {
        $owner = $document->owner;

        return $document->memberships()
            ->with('user:id,name,email')
            ->unless($manages, fn (Builder $memberships) => $memberships->where('status', MembershipStatus::Accepted))
            ->oldest('id')
            ->get()
            ->map(fn (DocumentMembership $membership): array => $this->toMemberRow($membership, $user, $manages))
            ->prepend([
                'id' => null,
                'name' => $owner->name,
                'email' => $manages ? $owner->email : null,
                'role' => DocumentRole::Editor->value,
                'status' => MembershipStatus::Accepted->value,
                'is_you' => $owner->id === $user->id,
            ])
            ->values();
    }

    /**
     * Shape a membership for the share dialog.
     *
     * @return array{id: int|null, name: string, email: string|null, role: string, status: string, is_you: bool}
     */
    private function toMemberRow(DocumentMembership $membership, User $user, bool $manages): array
    {
        return [
            'id' => $membership->id,
            'name' => $membership->user->name,
            'email' => $manages ? $membership->user->email : null,
            'role' => $membership->role->value,
            'status' => $membership->status->value,
            'is_you' => $membership->user_id === $user->id,
        ];
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
     * @return array{uuid: string, title: string, excerpt: string, updated_at_for_humans: string|null, owner: array{name: string}, is_owner: bool, role: string|null, shared_with_count: int, folder: array{uuid: string, name: string}|null}
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
            'shared_with_count' => (int) $document->shared_with_count,
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
