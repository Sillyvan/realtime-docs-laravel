<?php

namespace App\Events;

use App\Enums\DocumentRole;
use App\Models\Document;
use App\Models\DocumentMembership;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A member's access to the document changed while they may have it open.
 *
 * Everyone on the channel hears it: the member themselves so they can pick up
 * their new abilities (or leave), and whoever is managing the share dialog so
 * the member list stays honest.
 */
class DocumentAccessChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Document $document,
        public int $userId,
        public ?DocumentRole $role,
    ) {}

    /**
     * The member now holds a different role.
     */
    public static function granted(Document $document, DocumentMembership $membership): self
    {
        return new self($document, $membership->user_id, $membership->role);
    }

    /**
     * The member was removed, or removed themselves.
     */
    public static function revoked(Document $document, DocumentMembership $membership): self
    {
        return new self($document, $membership->user_id, null);
    }

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
     * @return array{user_id: int, role: string|null}
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'role' => $this->role?->value,
        ];
    }
}
