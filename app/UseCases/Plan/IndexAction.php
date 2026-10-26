<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * admin 用のプラン一覧をフィルタ付きで取得するユースケース。
 * プラン名のキーワード部分一致 + 状態で絞り込み、並び順の昇順(同値は作成日時の降順)で返す。
 * 各行の「受講者数」表示用に契約中の受講者数(users_count)を付与する。
 */
final class IndexAction
{
    public function __invoke(
        ?string $keyword,
        ?PlanStatus $status,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = Plan::query()->withCount('users');

        if ($keyword !== null && $keyword !== '') {
            $query->where('name', 'LIKE', '%'.$keyword.'%');
        }

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        return $query
            ->ordered()
            ->paginate($perPage)
            ->withQueryString();
    }
}
