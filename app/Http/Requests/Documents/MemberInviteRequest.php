<?php

namespace App\Http\Requests\Documents;

use App\Enums\DocumentRole;
use App\Enums\MembershipStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberInviteRequest extends FormRequest
{
    /**
     * The account behind the submitted email address, once resolved.
     */
    private ?User $invitee = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageMembers', $this->document());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', Rule::exists('users', 'email')],
            'role' => ['required', Rule::enum(DocumentRole::class)],
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
            'email.exists' => __('No account with that email.'),
        ];
    }

    /**
     * Reject an invitation for access the person already holds.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('email')) {
                return;
            }

            $document = $this->document();
            $invitee = $this->invitee();

            if ($invitee->id === $document->owner_id) {
                $validator->errors()->add('email', __('They already own this document.'));

                return;
            }

            $alreadyAMember = $document->memberships()
                ->where('user_id', $invitee->id)
                ->where('status', MembershipStatus::Accepted)
                ->exists();

            if ($alreadyAMember) {
                $validator->errors()->add('email', __('They already have access to this document.'));
            }
        }];
    }

    /**
     * The account being invited. Resolvable only once the email passed validation.
     */
    public function invitee(): User
    {
        return $this->invitee ??= User::where('email', $this->validated('email'))->firstOrFail();
    }

    /**
     * The document the invitation is for.
     */
    public function document(): Document
    {
        $document = $this->route('document');

        abort_unless($document instanceof Document, 404);

        return $document;
    }
}
