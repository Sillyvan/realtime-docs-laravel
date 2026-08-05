<?php

namespace App\Http\Requests\Documents;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scope' => ['sometimes', 'string', Rule::in(['all', 'mine', 'shared'])],
            'folder' => [
                'sometimes',
                'nullable',
                'string',
                Rule::when(
                    fn (): bool => $this->input('folder') !== 'root',
                    [Rule::exists('folders', 'uuid')->where('user_id', $this->user()->id)],
                ),
            ],
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort' => ['sometimes', 'string', Rule::in(['updated_at', 'created_at', 'title'])],
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'page' => ['sometimes', 'integer', 'min:1'],
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
     * The filters to apply, with every omitted filter resolved to its default.
     *
     * @return array{scope: string, folder: string|null, q: string|null, sort: string, direction: string}
     */
    public function filters(): array
    {
        $scope = $this->validated('scope', 'all');

        return [
            'scope' => $scope,
            // Folders file the documents you own, so they don't apply to shared ones.
            'folder' => $scope === 'shared' ? null : $this->validated('folder'),
            'q' => $this->validated('q'),
            'sort' => $this->validated('sort', 'updated_at'),
            'direction' => $this->validated('direction', 'desc'),
        ];
    }
}
