<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMcpCoverUploadRequest extends FormRequest
{
    /**
     * Yetkiyi rotadaki süreli imza taşır.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'afis' => ['required_without:arka_plan', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'arka_plan' => ['required_without:afis', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'afis.required_without' => 'En az bir görsel seçin.',
            'arka_plan.required_without' => 'En az bir görsel seçin.',
            '*.mimes' => 'JPG, PNG ya da WebP yükleyin.',
            '*.max' => 'Görsel en fazla 10 MB olabilir.',
        ];
    }
}
