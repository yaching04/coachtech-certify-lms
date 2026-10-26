<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\EnrollmentNote;
use Illuminate\Support\Facades\DB;

/**
 * コーチメモの更新ユースケース。本文のみ更新する(作成者・所属する受講登録は変更しない。管理者が編集しても作成者は書き換えない)。
 */
final class UpdateAction
{
    /**
     * @param array{body: string} $validated
     */
    public function __invoke(EnrollmentNote $note, array $validated): EnrollmentNote
    {
        DB::transaction(fn () => $note->update(['body' => $validated['body']]));

        return $note;
    }
}
