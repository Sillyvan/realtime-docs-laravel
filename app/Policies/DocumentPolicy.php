<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    /**
     * Determine whether the user can open the document.
     */
    public function view(User $user, Document $document): bool
    {
        return $document->roleFor($user) !== null;
    }

    /**
     * Determine whether the user can change the document's title or content.
     */
    public function update(User $user, Document $document): bool
    {
        return $document->roleFor($user)?->canEdit() ?? false;
    }

    /**
     * Determine whether the user can delete the document.
     */
    public function delete(User $user, Document $document): bool
    {
        return $document->owner_id === $user->id;
    }

    /**
     * Determine whether the user can invite, change roles, or revoke access.
     */
    public function manageMembers(User $user, Document $document): bool
    {
        return $document->owner_id === $user->id;
    }

    /**
     * Determine whether the user can move the document between folders.
     */
    public function file(User $user, Document $document): bool
    {
        return $document->owner_id === $user->id;
    }
}
