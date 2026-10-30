<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 通知一覧の入力検証。全ロール共通・本人の通知のみを表示するため、認可はログイン済であれば通す。
 *
 * - tab: all = 全件(既定) / unread = 未読のみ
 */
class IndexRequest extends FormRequest
{
    public const TAB_ALL = 'all';

    public const TAB_UNREAD = 'unread';

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
            'tab' => ['nullable', 'string', Rule::in([self::TAB_ALL, self::TAB_UNREAD])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tab' => 'タブ',
        ];
    }

    public function tab(): string
    {
        return $this->validated('tab') ?? self::TAB_ALL;
    }
}
