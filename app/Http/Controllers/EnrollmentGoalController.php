<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\Enrollment\EnrollmentGoalInvalidTransitionException;
use App\Http\Requests\EnrollmentGoal\StoreRequest;
use App\Http\Requests\EnrollmentGoal\UpdateRequest;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\UseCases\EnrollmentGoal\DestroyAction;
use App\UseCases\EnrollmentGoal\MarkAchievedAction;
use App\UseCases\EnrollmentGoal\StoreAction;
use App\UseCases\EnrollmentGoal\UnmarkAchievedAction;
use App\UseCases\EnrollmentGoal\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 個人目標 Controller(受講生本人のみ)。
 *
 * 目標の一覧 / 追加フォームは受講登録詳細画面(enrollments.show)に埋め込まれており、単独の一覧・詳細画面は持たない。
 * 閲覧(本人 / 担当コーチ / admin)は EnrollmentController::show 側、操作の認可は EnrollmentGoalPolicy で行う。
 * 全操作の完了後は受講登録詳細画面へ戻る。
 */
class EnrollmentGoalController extends Controller
{
    public function store(StoreRequest $request, Enrollment $enrollment, StoreAction $action): RedirectResponse
    {
        $action($enrollment, $request->validated());

        return $this->backToEnrollment($enrollment->id, '目標を追加しました。');
    }

    public function edit(EnrollmentGoal $goal): View
    {
        $this->authorize('update', $goal);

        return view('enrollment-goal.edit', [
            'goal' => $goal,
        ]);
    }

    public function update(UpdateRequest $request, EnrollmentGoal $goal, UpdateAction $action): RedirectResponse
    {
        $action($goal, $request->validated());

        return $this->backToEnrollment($goal->enrollment_id, '目標を更新しました。');
    }

    public function destroy(EnrollmentGoal $goal, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $goal);

        $action($goal);

        return $this->backToEnrollment($goal->enrollment_id, '目標を削除しました。');
    }

    /**
     * @throws EnrollmentGoalInvalidTransitionException
     */
    public function markAchieved(EnrollmentGoal $goal, MarkAchievedAction $action): RedirectResponse
    {
        $this->authorize('markAchieved', $goal);

        $action($goal);

        return $this->backToEnrollment($goal->enrollment_id, '目標を達成にしました。');
    }

    /**
     * @throws EnrollmentGoalInvalidTransitionException
     */
    public function unmarkAchieved(EnrollmentGoal $goal, UnmarkAchievedAction $action): RedirectResponse
    {
        $this->authorize('unmarkAchieved', $goal);

        $action($goal);

        return $this->backToEnrollment($goal->enrollment_id, '目標を未達成に戻しました。');
    }

    private function backToEnrollment(string $enrollmentId, string $message): RedirectResponse
    {
        return redirect()
            ->route('enrollments.show', $enrollmentId)
            ->with('success', $message);
    }
}
