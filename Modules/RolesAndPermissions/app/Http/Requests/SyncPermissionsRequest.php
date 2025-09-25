<?php

namespace Modules\RolesAndPermissions\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\RolesAndPermissions\app\Models\Permission;

class SyncPermissionsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'permission_ids'   => ['required', 'array'],
            'permission_ids.*' => ['exists:' . Permission::class . ',id'],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // We will use middleware to check for the 'roles.manage' permission.
        return true;
    }
}
