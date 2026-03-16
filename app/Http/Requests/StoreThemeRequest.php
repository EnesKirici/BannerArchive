<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreThemeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'config' => ['required', 'json'],
            'preview_color' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tema adı gereklidir.',
            'name.max' => 'Tema adı en fazla 100 karakter olabilir.',
            'config.required' => 'Tema yapılandırması gereklidir.',
            'config.json' => 'Tema yapılandırması geçerli bir JSON olmalıdır.',
        ];
    }
}
