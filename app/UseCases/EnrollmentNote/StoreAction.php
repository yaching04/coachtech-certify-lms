<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * コーチメモの追加ユースケース。操作したユーザー(コーチ / 管理者)を作成者として記録する。
 */
final class StoreAction
{
    /**
     * @param array{body: string} $validated
     */
    public function __invoke(Enrollment $enrollment, User $author, array $validated): EnrollmentNote
    {
        return DB::transaction(fn () => EnrollmentNote::create([
            'enrollment_id' => $enrollment->id,
            'author_id' => $author->id,
            'body' => $validated['body'],
        ]));
    }
}
