<?php

namespace Modules\Webhooks\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateWebhookRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'url' => ['required', 'url'],
            'events' => ['required', 'array'],
            'events.*' => ['string', 'in:user.created,user.deleted'], // Add more events as they are created
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }
}
