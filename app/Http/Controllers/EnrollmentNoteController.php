<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\EnrollmentNote\StoreRequest;
use App\Http\Requests\EnrollmentNote\UpdateRequest;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\UseCases\EnrollmentNote\DestroyAction;
use App\UseCases\EnrollmentNote\StoreAction;
use App\UseCases\EnrollmentNote\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * コーチメモ Controller(担当コーチ / 管理者のみ。受講生は全拒否)。
 *
 * メモの一覧 / 追加フォームは受講登録詳細画面(enrollments.show)に埋め込まれており、単独の一覧画面は持たない。
 * 編集のみ専用ページ。認可は EnrollmentNotePolicy(追加=担当コーチ/管理者、編集・削除=作成者本人/管理者)。
 * 全操作の完了後は受講登録詳細画面へ戻る。
 */
class EnrollmentNoteController extends Controller
{
    public function store(StoreRequest $request, Enrollment $enrollment, StoreAction $action): RedirectResponse
    {
        $action($enrollment, $request->user(), $request->validated());

        return $this->backToEnrollment($enrollment->id, 'メモを追加しました。');
    }

    public function edit(EnrollmentNote $note): View
    {
        $this->authorize('update', $note);

        return view('enrollment-note.edit', [
            'note' => $note,
        ]);
    }

    public function update(UpdateRequest $request, EnrollmentNote $note, UpdateAction $action): RedirectResponse
    {
        $action($note, $request->validated());

        return $this->backToEnrollment($note->enrollment_id, 'メモを更新しました。');
    }

    public function destroy(EnrollmentNote $note, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $note);

        $action($note);

        return $this->backToEnrollment($note->enrollment_id, 'メモを削除しました。');
    }

    private function backToEnrollment(string $enrollmentId, string $message): RedirectResponse
    {
        return redirect()
            ->route('enrollments.show', $enrollmentId)
            ->with('success', $message);
    }
}
