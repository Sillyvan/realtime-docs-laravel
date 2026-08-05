<?php

namespace App\Http\Requests\Documents;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $document = $this->route('document');

        if (! $this->user()->can('update', $document)) {
            return false;
        }

        // Filing is the owner's business, so only gate on it when a folder is sent.
        return ! $this->has('folder') || $this->user()->can('file', $document);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'folder' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists('folders', 'uuid')->where('user_id', $this->user()->id),
            ],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'folder.exists' => __('That folder does not exist.'),
        ];
    }

    /**
     * The validated attributes, with the folder uuid resolved to a foreign key.
     *
     * @return array<string, mixed>
     */
    public function documentAttributes(): array
    {
        $attributes = $this->safe()->only('title');

        if ($this->has('folder')) {
            $attributes['folder_id'] = $this->user()->folders()
                ->where('uuid', $this->validated('folder'))
                ->value('id');
        }

        return $attributes;
    }
}
