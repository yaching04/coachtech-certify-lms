<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * パスワード変更ユースケース。現在のパスワードの確認は FormRequest 側で済んでいる前提。
 * User モデルの `hashed` キャストによりハッシュ化して保存される。
 */
final class UpdatePasswordAction
{
    public function __invoke(User $user, string $newPassword): User
    {
        DB::transaction(fn () => $user->update(['password' => $newPassword]));

        return $user;
    }
}
