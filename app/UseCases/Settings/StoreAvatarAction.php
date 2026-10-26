<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * アイコン画像の登録ユースケース。
 *
 * public ディスクの `avatars/{user_id}/` 配下へ保存し(リサイズ・圧縮はしない)、公開 URL を users.avatar_url に保存する。
 * 差し替え時は、そのユーザーの古い画像ファイルを削除する。
 */
final class StoreAvatarAction
{
    public const DISK = 'public';

    public static function directoryFor(User $user): string
    {
        return 'avatars/'.$user->id;
    }

    public function __invoke(User $user, UploadedFile $file): User
    {
        $disk = Storage::disk(self::DISK);
        $directory = self::directoryFor($user);

        $path = $disk->putFile($directory, $file);

        try {
            DB::transaction(fn () => $user->update(['avatar_url' => $disk->url($path)]));
        } catch (\Throwable $e) {
            $disk->delete($path);

            throw $e;
        }

        // 新しい画像以外(差し替え前のファイル)を片付ける
        foreach ($disk->files($directory) as $existing) {
            if ($existing !== $path) {
                $disk->delete($existing);
            }
        }

        return $user;
    }
}
