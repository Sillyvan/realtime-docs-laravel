<?php

namespace App\Broadcasting;

use App\Models\Document;
use App\Models\User;

class DocumentChannel
{
    /**
     * Authenticate the user's access to the document's presence channel.
     *
     * The return value doubles as the member's presence payload, so every other
     * client on the channel learns who is here and what they may do. Peers use
     * the role to decide whose document updates they trust.
     *
     * @return array{id: int, name: string, role: string}|false
     */
    public function join(User $user, Document $document): array|false
    {
        $role = $document->roleFor($user);

        if ($role === null) {
            return false;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $role->value,
        ];
    }
}
