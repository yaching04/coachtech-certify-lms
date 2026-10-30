<?php

declare(strict_types=1);

namespace App\Http\Requests\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 運営お知らせの配信リクエスト(admin のみ)。タイトル / 本文(プレーンテキスト)/ 配信対象を受け付ける。
 *
 * - 資格指定のときだけ target_certification_id、ユーザー指定のときだけ target_user_id を必須にする
 * - 選んだタイプに対応しない欄の値は prepareForValidation で捨てる(画面は両方のセレクトを常時表示するため)
 * - ユーザー指定の対象は受講中の受講生のみ(コーチ向け配信はスコープ外)
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Announcement::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $type = $this->input('target_type');

        $this->merge([
            'target_certification_id' => $type === AnnouncementTargetType::Certification->value
                ? $this->input('target_certification_id')
                : null,
            'target_user_id' => $type === AnnouncementTargetType::User->value
                ? $this->input('target_user_id')
                : null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'target_type' => ['required', Rule::enum(AnnouncementTargetType::class)],
            'target_certification_id' => [
                Rule::requiredIf(fn () => $this->input('target_type') === AnnouncementTargetType::Certification->value),
                'nullable',
                'string',
                Rule::exists('certifications', 'id'),
            ],
            'target_user_id' => [
                Rule::requiredIf(fn () => $this->input('target_type') === AnnouncementTargetType::User->value),
                'nullable',
                'string',
                Rule::exists('users', 'id')
                    ->where('role', UserRole::Student->value)
                    ->where('status', UserStatus::InProgress->value)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
            'body' => '本文',
            'target_type' => '配信対象',
            'target_certification_id' => '対象資格',
            'target_user_id' => '対象受講生',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_certification_id.required' => '配信対象が「資格指定」の場合は対象資格を選択してください。',
            'target_user_id.required' => '配信対象が「ユーザー指定」の場合は対象受講生を選択してください。',
            'target_user_id.exists' => '対象受講生には受講中の受講生を選択してください。',
        ];
    }
}
