<?php

namespace App\Http\Requests\Documents;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DocumentContentUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('document'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'state' => ['present', 'nullable', 'string', 'max:4000000', $this->base64()],
            'content_html' => ['present', 'nullable', 'string', 'max:500000'],
            'content_text' => ['present', 'nullable', 'string', 'max:500000'],
        ];
    }

    /**
     * The snapshot to store, with the Yjs state decoded back to the raw bytes
     * the `state` column holds. The client sends it base64-encoded because the
     * update is binary and the request body is JSON.
     *
     * @return array{state: string|null, content_html: string|null, content_text: string|null}
     */
    public function contentAttributes(): array
    {
        $state = $this->validated('state');

        return [
            ...$this->safe()->only(['content_html', 'content_text']),
            'state' => $state === null ? null : base64_decode($state, true),
        ];
    }

    /**
     * Reject anything that is not strictly valid base64.
     */
    private function base64(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || base64_decode($value, true) === false) {
                $fail(__('The document state is malformed.'));
            }
        };
    }
}
