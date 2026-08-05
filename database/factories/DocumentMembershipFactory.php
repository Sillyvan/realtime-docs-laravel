<?php

namespace Database\Factories;

use App\Enums\DocumentRole;
use App\Enums\MembershipStatus;
use App\Models\Document;
use App\Models\DocumentMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentMembership>
 */
class DocumentMembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'user_id' => User::factory(),
            'invited_by_id' => fn (array $attributes) => Document::whereKey($attributes['document_id'])->value('owner_id'),
            'role' => DocumentRole::Viewer,
            'status' => MembershipStatus::Pending,
            'responded_at' => null,
        ];
    }

    /**
     * Indicate that the invitation was accepted.
     */
    public function accepted(): static
    {
        return $this->state([
            'status' => MembershipStatus::Accepted,
            'responded_at' => now(),
        ]);
    }

    /**
     * Indicate that the invitation is still awaiting a response.
     */
    public function pending(): static
    {
        return $this->state([
            'status' => MembershipStatus::Pending,
            'responded_at' => null,
        ]);
    }

    /**
     * Indicate that the invitation was declined.
     */
    public function declined(): static
    {
        return $this->state([
            'status' => MembershipStatus::Declined,
            'responded_at' => now(),
        ]);
    }

    /**
     * Indicate that the membership grants editing rights.
     */
    public function editor(): static
    {
        return $this->state(['role' => DocumentRole::Editor]);
    }

    /**
     * Indicate that the membership is read-only.
     */
    public function viewer(): static
    {
        return $this->state(['role' => DocumentRole::Viewer]);
    }
}
