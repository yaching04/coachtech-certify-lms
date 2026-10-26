<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * プロフィール更新ユースケース。氏名 / 自己紹介(コーチは固定面談 URL も)のみ更新する。
 */
final class UpdateProfileAction
{
    /**
     * @param array{name: string, bio?: ?string, meeting_url?: string} $validated
     */
    public function __invoke(User $user, array $validated): User
    {
        $attributes = [
            'name' => $validated['name'],
            'bio' => $validated['bio'] ?? null,
        ];

        if (array_key_exists('meeting_url', $validated)) {
            $attributes['meeting_url'] = $validated['meeting_url'];
        }

        DB::transaction(fn () => $user->update($attributes));

        return $user;
    }
}
