<?php

namespace App\Http\Requests\Customer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StartConversationRequest extends FormRequest
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
            'customer_name'  => ['required', 'string', 'max:100'],
            'customer_email' => ['nullable', 'email', 'max:255'],
        ];
    }

    // Override the validationData method to include the authenticated user's name and email
    // public function validationData(): array
    // {
    //     return array_merge(parent::validationData(), [
    //         'customer_name'  => $this->user()->full_name,
    //         'customer_email' => $this->user()->email,
    //     ]);
    // }
}
