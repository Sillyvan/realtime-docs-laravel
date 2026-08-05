<?php

namespace App\Policies;

use App\Models\Folder;
use App\Models\User;

class FolderPolicy
{
    /**
     * Determine whether the user can see the folder.
     */
    public function view(User $user, Folder $folder): bool
    {
        return $folder->user_id === $user->id;
    }

    /**
     * Determine whether the user can rename the folder.
     */
    public function update(User $user, Folder $folder): bool
    {
        return $folder->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the folder.
     */
    public function delete(User $user, Folder $folder): bool
    {
        return $folder->user_id === $user->id;
    }
}
