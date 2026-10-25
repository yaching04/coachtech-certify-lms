<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanNotDeletableException;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

/**
 * プランを物理削除するユースケース。
 * 参照整合性を守るため、下書きかつ受講者(退会済みを含む)・プラン履歴が紐づかないプランのみ削除できる。
 */
final class DestroyAction
{
    /**
     * @throws PlanNotDeletableException 下書き以外 / 受講者・プラン履歴が紐づくプランは削除不可
     */
    public function __invoke(Plan $plan): void
    {
        if ($plan->status !== PlanStatus::Draft) {
            throw PlanNotDeletableException::notDraft();
        }

        if ($plan->users()->withTrashed()->exists() || $plan->userPlanLogs()->exists()) {
            throw PlanNotDeletableException::hasUsers();
        }

        DB::transaction(fn () => $plan->delete());
    }
}
