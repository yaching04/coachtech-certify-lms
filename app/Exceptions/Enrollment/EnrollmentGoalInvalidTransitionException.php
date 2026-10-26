<?php

declare(strict_types=1);

namespace App\Exceptions\Enrollment;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 個人目標の達成状態の切替が不正な場合の例外(HTTP 409)。
 * 達成済の目標を再度達成にする / 未達成の目標の達成を解除する操作で throw する。
 */
final class EnrollmentGoalInvalidTransitionException extends ConflictHttpException
{
    public static function forMarkAchieved(): self
    {
        return new self('未達成の目標のみ達成にできます。');
    }

    public static function forUnmarkAchieved(): self
    {
        return new self('達成済の目標のみ未達成に戻せます。');
    }
}
