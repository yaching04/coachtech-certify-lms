<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * アイコン画像アップロードリクエスト。PNG / JPG / WebP、2MB(2048KB)以内に制限する(SVG など他の形式は不可)。
 */
class StoreAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'avatar' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'avatar' => 'アイコン画像',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.required' => 'アップロードする画像を選択してください。',
            'avatar.mimes' => 'アイコン画像は PNG / JPG / WebP 形式を指定してください。',
            'avatar.max' => 'アイコン画像は 2MB 以内にしてください。',
        ];
    }
}
