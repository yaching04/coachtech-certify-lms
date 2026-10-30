<?php

declare(strict_types=1);

namespace App\Http\Requests\QaThread;

use App\Models\QaThread;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 質問掲示板一覧の絞り込み入力検証。受講生 / コーチ / admin(モデレーション)共通で利用される。
 *
 * - certification_id: 資格で絞り込み(閲覧範囲外の資格を指定しても scopeVisibleTo で 0 件になるだけ)
 * - status: '' = すべて / unresolved = 未解決 / resolved = 解決済
 * - keyword: スレッドのタイトル・本文・回答本文の部分一致
 */
class IndexRequest extends FormRequest
{
    public const STATUS_UNRESOLVED = 'unresolved';

    public const STATUS_RESOLVED = 'resolved';

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', QaThread::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'certification_id' => ['nullable', 'string', 'max:26'],
            'status' => ['nullable', 'string', Rule::in([self::STATUS_UNRESOLVED, self::STATUS_RESOLVED])],
            'keyword' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'certification_id' => '資格',
            'status' => '解決状態',
            'keyword' => 'キーワード',
        ];
    }

    /**
     * 画面(_filter.blade.php)の選択状態判定に合わせ、未指定は空文字に正規化して返す。
     *
     * @return array{certification_id: string, status: string, keyword: string}
     */
    public function filters(): array
    {
        return [
            'certification_id' => (string) $this->input('certification_id', ''),
            'status' => (string) $this->input('status', ''),
            'keyword' => trim((string) $this->input('keyword', '')),
        ];
    }
}
