<?php

declare(strict_types=1);

namespace App\Http\Requests\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 回答編集リクエスト。親スレッドを閲覧可能、かつ回答者本人の場合のみ許可する。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $thread = $this->route('thread');
        $reply = $this->route('reply');
        $user = $this->user();

        return $thread instanceof QaThread
            && $reply instanceof QaReply
            && $user !== null
            && $user->can('view', $thread)
            && $user->can('update', $reply);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => '回答本文',
        ];
    }
}
