<?php

namespace App\Http\Requests\Customer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
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
            'body'            => ['nullable', 'string', 'max:5000', 'required_without:attachments'],
            'attachments'     => ['nullable', 'array', 'max:5'],
            'attachments.*'   => [
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,txt,zip',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required_without' => 'A message body is required when no attachments are provided.',
        ];
    }
}
