<?php

namespace App\Http\Controllers;

use App\Http\Requests\Documents\DocumentUpdateRequest;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    /**
     * List the documents the user owns or has been given access to.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $documents = Document::query()
            ->forUser($user)
            ->with([
                'owner:id,name',
                'memberships' => fn (Relation $memberships) => $memberships->where('user_id', $user->id),
            ])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Document $document): array => $this->toListItem($document, $user));

        return Inertia::render('documents/index', [
            'documents' => $documents,
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

        return Inertia::render('documents/show', [
            'document' => [
                'uuid' => $document->uuid,
                'title' => $document->title,
                'content_html' => $document->content_html ?? '',
                'updated_at_for_humans' => $document->updated_at?->diffForHumans(),
            ],
            'can' => [
                'update' => $user->can('update', $document),
                'delete' => $user->can('delete', $document),
            ],
        ]);
    }

    /**
     * Rename the document.
     */
    public function update(DocumentUpdateRequest $request, Document $document): RedirectResponse
    {
        $document->update($request->validated());

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
     * Shape a document for the index list.
     *
     * @return array{uuid: string, title: string, excerpt: string, updated_at_for_humans: string|null, owner: array{name: string}, is_owner: bool, role: string|null}
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
        ];
    }
}
