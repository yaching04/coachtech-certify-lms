<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Models\Announcement;

/**
 * 運営お知らせ 1 件の配信内容・配信実績を返すユースケース(admin 用)。
 */
final class ShowAction
{
    public function __invoke(Announcement $announcement): Announcement
    {
        return $announcement->load(['targetCertification', 'targetUser', 'createdBy']);
    }
}
