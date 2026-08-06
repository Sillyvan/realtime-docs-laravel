<?php

namespace App\Events;

use App\Models\Document;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The title is a column rather than part of the CRDT, so a rename is the one
 * edit peers cannot pick up from a whisper — the server has to tell them.
 */
class DocumentTitleUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Document $document) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('documents.'.$this->document->uuid),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array{uuid: string, title: string}
     */
    public function broadcastWith(): array
    {
        return [
            'uuid' => $this->document->uuid,
            'title' => $this->document->title,
        ];
    }
}
