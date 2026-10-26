<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * アイコン画像の削除ユースケース。avatar_url を null に戻し(未設定表示 = 氏名のイニシャル)、アップロード済みのファイルも削除する。
 * 画像が未設定でもエラーにしない(冪等)。
 */
final class DestroyAvatarAction
{
    public function __invoke(User $user): User
    {
        DB::transaction(fn () => $user->update(['avatar_url' => null]));

        Storage::disk(StoreAvatarAction::DISK)->deleteDirectory(StoreAvatarAction::directoryFor($user));

        return $user;
    }
}
