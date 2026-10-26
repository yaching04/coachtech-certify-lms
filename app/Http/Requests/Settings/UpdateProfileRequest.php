<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

/**
 * プロフィール更新リクエスト(全ロール共通)。
 *
 * 氏名と自己紹介のみ更新でき、メール / ロール / ステータス等は入力されても無視する(rules に含めないため validated() に出ない)。
 * 固定面談 URL(meeting_url)はコーチのみ受け付け、受講生 / 管理者が送っても無視する。
 */
class UpdateProfileRequest extends FormRequest
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
        $rules = [
            'name' => ['required', 'string', 'max:50'],
            'bio' => ['nullable', 'string', 'max:1000'],
        ];

        if ($this->user()?->role === UserRole::Coach) {
            $rules['meeting_url'] = ['required', 'string', 'url', 'max:500'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '氏名',
            'bio' => '自己紹介',
            'meeting_url' => '固定面談 URL',
        ];
    }
}
