<?php

use App\Broadcasting\DocumentChannel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
 * Joined as `presence-documents.{uuid}`: the editor's "who's here" avatars, and
 * from Phase 7 on, the transport for the Yjs document updates themselves.
 */
Broadcast::channel('documents.{document}', DocumentChannel::class);
