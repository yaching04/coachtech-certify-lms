<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Models\Announcement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 運営お知らせの配信履歴を配信日時の新しい順で返すユースケース(admin 用)。
 */
final class IndexAction
{
    public const PER_PAGE = 20;

    /**
     * @return LengthAwarePaginator<Announcement>
     */
    public function __invoke(): LengthAwarePaginator
    {
        return Announcement::query()
            ->with(['targetCertification', 'targetUser', 'createdBy'])
            ->orderByDesc('dispatched_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }
}
