<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;
use Illuminate\Support\Facades\DB;

/**
 * 個人目標の編集ユースケース。タイトル / 詳細 / 目標期日のみ更新し、達成状態は変えない。
 */
final class UpdateAction
{
    /**
     * @param array{title: string, description?: ?string, target_date?: ?string} $validated
     */
    public function __invoke(EnrollmentGoal $goal, array $validated): EnrollmentGoal
    {
        DB::transaction(fn () => $goal->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'target_date' => $validated['target_date'] ?? null,
        ]));

        return $goal;
    }
}
