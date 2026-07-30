<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'messages_since' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messagesSince(): int
    {
        return (int) $this->validated('messages_since', 0);
    }
}
