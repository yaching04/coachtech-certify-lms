<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Http\Requests\QaThread\IndexRequest;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * 質問掲示板の一覧取得ユースケース(受講生 / コーチ / 管理者モデレーション共通)。
 *
 * 閲覧範囲は `QaThread::scopeVisibleTo` でロール別に絞り、資格 / 解決状態 / キーワードで絞り込んだうえで
 * 新着順(created_at DESC)に 1 ページ 20 件でページネーションする。カード描画に必要な投稿者・資格・回答件数は
 * with / withCount でまとめて取得し、件数増加に対して N+1 クエリを発生させない。
 */
final class IndexAction
{
    public const PER_PAGE = 20;

    /**
     * @param array{certification_id?: string, status?: string, keyword?: string} $filters
     *
     * @return LengthAwarePaginator<int, QaThread>
     */
    public function __invoke(User $viewer, array $filters): LengthAwarePaginator
    {
        $certificationId = $filters['certification_id'] ?? '';
        $status = match ($filters['status'] ?? '') {
            IndexRequest::STATUS_UNRESOLVED => QaThreadStatus::Open,
            IndexRequest::STATUS_RESOLVED => QaThreadStatus::Resolved,
            default => null,
        };

        return QaThread::query()
            ->visibleTo($viewer)
            ->when($certificationId !== '', fn ($q) => $q->where('certification_id', $certificationId))
            ->when($status !== null, fn ($q) => $q->where('status', $status->value))
            ->keyword($filters['keyword'] ?? null)
            ->with(['user', 'certification'])
            ->withCount('replies')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * 絞り込み用の資格一覧(admin = 全資格 / coach = 担当かつ公開中 / student = 公開中)を資格名順で返す。
     *
     * @return Collection<int, Certification>
     */
    public function filterableCertifications(User $viewer): Collection
    {
        $query = Certification::query()->orderBy('name');

        if ($viewer->role !== UserRole::Admin) {
            $query->where('status', CertificationStatus::Published->value);
        }

        if ($viewer->role === UserRole::Coach) {
            $query->whereIn('id', $viewer->coachingCertificationIds());
        }

        return $query->get();
    }
}
