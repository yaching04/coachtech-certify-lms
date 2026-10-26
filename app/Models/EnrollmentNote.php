<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EnrollmentNoteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 受講登録(Enrollment)配下のコーチメモを表す Model。担当コーチ / 管理者のみが扱う内部業務記録(受講生には非公開)。
 *
 * 本文のみのフラットなメモ(ステータス・添付・コメントなし)。物理削除のみ。
 * 作成者(author)は退職等で論理削除されたユーザーでも名前を表示できるよう withTrashed で取得する。
 *
 * 関連: Enrollment(belongsTo / 論理削除済みも取得)、User(author, belongsTo)
 */
class EnrollmentNote extends Model
{
    /** @use HasFactory<EnrollmentNoteFactory> */
    use HasFactory, HasUlids;

    public const BODY_MAX_LENGTH = 2000;

    protected $fillable = [
        'enrollment_id',
        'author_id',
        'body',
    ];

    /**
     * 論理削除済みの受講登録も取得する(Policy が「親が削除済み」を判定できるようにするため)。
     *
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id')->withTrashed();
    }
}
