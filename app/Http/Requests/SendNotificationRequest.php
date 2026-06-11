<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendNotificationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'channel' => ['required', 'string', 'in:sms,email'],
            'message' => ['required', 'string', 'min:1', 'max:5000'],
            'recipient_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'recipient_ids.*' => ['required', 'integer', 'exists:users,id'],
            'priority' => ['required', 'string', 'in:transactional,marketing'],
        ];
    }
}
