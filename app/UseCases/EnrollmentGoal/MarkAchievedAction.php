<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Exceptions\Enrollment\EnrollmentGoalInvalidTransitionException;
use App\Models\EnrollmentGoal;
use Illuminate\Support\Facades\DB;

/**
 * 個人目標の達成マークユースケース。未達成の目標のみ、達成日時を現在時刻にして達成済にする。
 */
final class MarkAchievedAction
{
    /**
     * @throws EnrollmentGoalInvalidTransitionException
     */
    public function __invoke(EnrollmentGoal $goal): EnrollmentGoal
    {
        if ($goal->isAchieved()) {
            throw EnrollmentGoalInvalidTransitionException::forMarkAchieved();
        }

        DB::transaction(fn () => $goal->update(['achieved_at' => now()]));

        return $goal;
    }
}
