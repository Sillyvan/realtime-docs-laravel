<?php

namespace App\Events;

use App\Models\DocumentMembership;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Someone has been invited to a document, announced on the invitee's own
 * channel so the sidebar badge counts up wherever they are in the app.
 */
class InvitationReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public DocumentMembership $membership) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->membership->user_id),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array{document: array{uuid: string, title: string}, role: string, invited_by: string|null}
     */
    public function broadcastWith(): array
    {
        return [
            'document' => [
                'uuid' => $this->membership->document->uuid,
                'title' => $this->membership->document->title,
            ],
            'role' => $this->membership->role->value,
            'invited_by' => $this->membership->invitedBy?->name,
        ];
    }
}
