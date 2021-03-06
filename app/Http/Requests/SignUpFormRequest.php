<?php

namespace App\Http\Requests;

use App\Rules\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SignUpFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            "email" => "required_if:description,signup_with_email|email",
            "phone" => ["required_if:description,signup_with_phone", new Phone],
            "description" => "required|in:signup_with_email,signup_with_phone",
            "password" => ["required", Password::min(8)->mixedCase()->numbers()]
        ];
    }
}
