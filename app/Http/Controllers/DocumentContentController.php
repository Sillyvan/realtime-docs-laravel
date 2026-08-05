<?php

namespace App\Http\Controllers;

use App\Http\Requests\Documents\DocumentContentUpdateRequest;
use App\Models\Document;
use Illuminate\Http\Response;

class DocumentContentController extends Controller
{
    /**
     * Persist the editor's autosave snapshot.
     */
    public function update(DocumentContentUpdateRequest $request, Document $document): Response
    {
        $document->update($request->validated());

        return response()->noContent();
    }
}
