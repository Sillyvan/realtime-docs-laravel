<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $text = fake()->paragraph();

        return [
            'owner_id' => User::factory(),
            'folder_id' => null,
            'title' => fake()->sentence(3),
            'state' => null,
            'content_html' => '<p>'.$text.'</p>',
            'content_text' => $text,
        ];
    }

    /**
     * File the document in the given folder, owned by that folder's owner.
     */
    public function inFolder(Folder $folder): static
    {
        return $this->state([
            'folder_id' => $folder->id,
            'owner_id' => $folder->user_id,
        ]);
    }

    /**
     * Indicate that the document has no content yet.
     */
    public function empty(): static
    {
        return $this->state([
            'title' => 'Untitled document',
            'content_html' => null,
            'content_text' => null,
        ]);
    }
}
