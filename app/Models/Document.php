<?php

namespace App\Models;

use App\Enums\DocumentRole;
use App\Enums\MembershipStatus;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $owner_id
 * @property int|null $folder_id
 * @property string $title
 * @property string|null $state
 * @property string|null $content_html
 * @property string|null $content_text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $shared_with_count accepted memberships, when the index query counts them
 */
#[Fillable(['title', 'folder_id', 'state', 'content_html', 'content_text'])]
#[RouteKey('uuid')]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * The user who created the document; always an editor, never removable.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The owner's folder the document is filed in, if any.
     *
     * @return BelongsTo<Folder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    /**
     * Every membership row, whatever its status.
     *
     * @return HasMany<DocumentMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(DocumentMembership::class);
    }

    /**
     * The users who accepted an invitation to the document.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'document_memberships')
            ->withPivot(['role', 'status'])
            ->wherePivot('status', MembershipStatus::Accepted->value);
    }

    /**
     * Resolve the role the given user holds on this document, if any.
     */
    public function roleFor(User $user): ?DocumentRole
    {
        if ($this->owner_id === $user->id) {
            return DocumentRole::Editor;
        }

        $membership = $this->relationLoaded('memberships')
            ? $this->memberships->firstWhere('user_id', $user->id)
            : $this->memberships()->where('user_id', $user->id)->first();

        if ($membership?->status !== MembershipStatus::Accepted) {
            return null;
        }

        return $membership->role;
    }

    /**
     * Scope the query to documents the user owns or has accepted a share of.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        $query->where(function (Builder $query) use ($user) {
            $query->where('owner_id', $user->id)
                ->orWhereHas('memberships', function (Builder $memberships) use ($user) {
                    $memberships->where('user_id', $user->id)
                        ->where('status', MembershipStatus::Accepted);
                });
        });
    }

    /**
     * Scope the query to documents the user owns.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function ownedBy(Builder $query, User $user): void
    {
        $query->where('owner_id', $user->id);
    }

    /**
     * Scope the query to documents shared with the user and accepted by them.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function sharedWith(Builder $query, User $user): void
    {
        $query->whereHas('memberships', function (Builder $memberships) use ($user) {
            $memberships->where('user_id', $user->id)
                ->where('status', MembershipStatus::Accepted);
        });
    }

    /**
     * Scope the query to a folder uuid, or to the root with the `root` keyword.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function inFolder(Builder $query, ?string $uuid): void
    {
        if (blank($uuid)) {
            return;
        }

        if ($uuid === 'root') {
            $query->whereNull('folder_id');

            return;
        }

        $query->whereRelation('folder', 'uuid', $uuid);
    }

    /**
     * Scope the query to documents matching the term in their title or body.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $like = '%'.$term.'%';

        $query->where(function (Builder $query) use ($like) {
            $query->where('title', 'like', $like)
                ->orWhere('content_text', 'like', $like);
        });
    }
}
