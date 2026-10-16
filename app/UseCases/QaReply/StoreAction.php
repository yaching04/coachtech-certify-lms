<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class StoreAction
{
    /**
     * @param array{body: string} $validated
     */
    public function __invoke(User $user, QaThread $thread, array $validated): QaReply
    {
        return DB::transaction(fn () => $thread->replies()->create([
            'user_id' => $user->id,
            'body' => $validated['body'],
        ]));
    }
}
