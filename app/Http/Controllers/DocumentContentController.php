<?php

namespace App\Http\Controllers;

use App\Http\Requests\Documents\DocumentContentUpdateRequest;
use App\Models\Document;
use Illuminate\Http\Response;

class DocumentContentController extends Controller
{
    /**
     * Persist the editor's autosave snapshot.
     *
     * Every client's Yjs state already contains every peer's edits, so concurrent
     * saves converge and the last write is safe to take as-is.
     */
    public function update(DocumentContentUpdateRequest $request, Document $document): Response
    {
        $document->update($request->contentAttributes());

        return response()->noContent();
    }
}
