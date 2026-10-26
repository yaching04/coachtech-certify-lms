<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Exceptions\Enrollment\EnrollmentGoalInvalidTransitionException;
use App\Models\EnrollmentGoal;
use Illuminate\Support\Facades\DB;

/**
 * 個人目標の達成解除ユースケース。達成済の目標のみ、達成日時を消して未達成に戻す。
 */
final class UnmarkAchievedAction
{
    /**
     * @throws EnrollmentGoalInvalidTransitionException
     */
    public function __invoke(EnrollmentGoal $goal): EnrollmentGoal
    {
        if (! $goal->isAchieved()) {
            throw EnrollmentGoalInvalidTransitionException::forUnmarkAchieved();
        }

        DB::transaction(fn () => $goal->update(['achieved_at' => null]));

        return $goal;
    }
}
