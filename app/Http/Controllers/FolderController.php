<?php

namespace App\Http\Controllers;

use App\Http\Requests\Documents\FolderRequest;
use App\Models\Folder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class FolderController extends Controller
{
    /**
     * Create a folder for the authenticated user.
     */
    public function store(FolderRequest $request): RedirectResponse
    {
        $folder = $request->user()->folders()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Folder created.')]);

        return to_route('documents.index', ['folder' => $folder->uuid]);
    }

    /**
     * Rename a folder.
     */
    public function update(FolderRequest $request, Folder $folder): RedirectResponse
    {
        $folder->update($request->validated());

        return back();
    }

    /**
     * Delete a folder, leaving its documents at the root.
     */
    public function destroy(Folder $folder): RedirectResponse
    {
        Gate::authorize('delete', $folder);

        $folder->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Folder deleted.')]);

        // The deleted folder can't stay in the query string as a filter.
        return to_route('documents.index');
    }
}
