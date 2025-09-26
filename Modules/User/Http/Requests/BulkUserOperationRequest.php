<?php

namespace Modules\User\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkUserOperationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer', 'exists:users,id'],
            'action' => ['required', 'string', 'in:delete,activate,deactivate,suspend'],
            'status' => ['required_if:action,activate,deactivate,suspend', 'string', 'in:active,inactive,suspended'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_ids.required' => 'At least one user must be selected.',
            'user_ids.array' => 'User IDs must be provided as an array.',
            'user_ids.min' => 'At least one user must be selected.',
            'user_ids.*.integer' => 'Each user ID must be a valid integer.',
            'user_ids.*.exists' => 'One or more selected users do not exist.',
            'action.required' => 'An action must be specified.',
            'action.in' => 'The action must be one of: delete, activate, deactivate, suspend.',
            'status.required_if' => 'Status is required for status change operations.',
            'status.in' => 'The status must be one of: active, inactive, suspended.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_ids' => 'selected users',
            'action' => 'bulk operation action',
            'status' => 'new status',
        ];
    }
}
