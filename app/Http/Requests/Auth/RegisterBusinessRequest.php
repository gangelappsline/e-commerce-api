<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class RegisterBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'owner_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
            'phone' => ['nullable', 'string', 'max:40'],
            'business_name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'alpha_dash:ascii', 'min:3', 'max:100', 'unique:businesses,slug'],
            'business_type' => ['required', Rule::in(config('shopline.supported_business_types'))],
            'currency' => ['sometimes', 'string', 'size:3', 'alpha:ascii'],
            'country' => ['nullable', 'string', 'max:100'],
        ];
    }
}
