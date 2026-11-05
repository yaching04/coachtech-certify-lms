<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use Database\Factories\QaThreadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 質問掲示板(qa-board)の質問スレッドを表す Model。
 *
 * 関連: Certification(紐付け資格) / User(投稿者 = 受講生) / QaReply(回答)
 * scope: visibleTo(User)(admin = 全件 / coach = 担当かつ公開中資格 / student = 公開中資格) / keyword(?string)(タイトル・本文の部分一致)
 */
class QaThread extends Model
{
    /** @use HasFactory<QaThreadFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'certification_id',
        'user_id',
        'title',
        'body',
        'status',
        'resolved_at',
    ];

    protected $casts = [
        'status' => QaThreadStatus::class,
        'resolved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Certification, $this>
     */
    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<QaReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(QaReply::class);
    }

    /**
     * 閲覧者のロールに応じて閲覧可能なスレッドに絞り込む。
     *
     * - admin: 全資格(公開停止中を含む)
     * - coach: 担当資格かつ公開中の資格のみ
     * - student: 公開中の資格すべて
     *
     * @param Builder<QaThread> $query
     *
     * @return Builder<QaThread>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->role === UserRole::Admin) {
            return $query;
        }

        $query->whereHas(
            'certification',
            fn (Builder $q) => $q->where('status', CertificationStatus::Published->value),
        );

        return match ($user->role) {
            UserRole::Coach => $query->whereIn('certification_id', $user->coachingCertificationIds()),
            UserRole::Student => $query,
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * タイトル・本文の部分一致で絞り込む。
     *
     * @param Builder<QaThread> $query
     *
     * @return Builder<QaThread>
     */
    public function scopeKeyword(Builder $query, ?string $keyword): Builder
    {
        if ($keyword === null || $keyword === '') {
            return $query;
        }

        $like = '%'.addcslashes($keyword, '%_\\').'%';

        return $query->where(
            fn (Builder $q) => $q->where('title', 'LIKE', $like)->orWhere('body', 'LIKE', $like),
        );
    }

    public function isResolved(): bool
    {
        return $this->status === QaThreadStatus::Resolved;
    }
}
