<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Settings;

use App\Models\User;
use App\UseCases\Settings\DestroyAvatarAction;
use App\UseCases\Settings\StoreAvatarAction;
use App\UseCases\Settings\UpdatePasswordAction;
use App\UseCases\Settings\UpdateProfileAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 設定系 UseCase(プロフィール更新 / アイコン登録・削除 / パスワード変更)の単体検証。
 */
class ActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_profile_does_not_touch_meeting_url_when_absent(): void
    {
        $user = User::factory()->coach()->create(['meeting_url' => 'https://meet.example.com/keep']);

        (new UpdateProfileAction)($user, ['name' => '名前', 'bio' => null]);

        $this->assertSame('名前', $user->refresh()->name);
        $this->assertSame('https://meet.example.com/keep', $user->meeting_url);
    }

    public function test_store_avatar_saves_under_user_directory_and_sets_url(): void
    {
        Storage::fake('public');
        $user = User::factory()->student()->create();

        (new StoreAvatarAction)($user, UploadedFile::fake()->image('a.png'));

        $files = Storage::disk('public')->files("avatars/{$user->id}");
        $this->assertCount(1, $files);
        $this->assertSame(Storage::disk('public')->url($files[0]), $user->refresh()->avatar_url);
    }

    public function test_destroy_avatar_clears_url_and_files(): void
    {
        Storage::fake('public');
        $user = User::factory()->student()->create();
        (new StoreAvatarAction)($user, UploadedFile::fake()->image('a.png'));

        (new DestroyAvatarAction)($user);

        $this->assertNull($user->refresh()->avatar_url);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_destroy_avatar_keeps_static_seed_asset_untouched(): void
    {
        Storage::fake('public');
        $user = User::factory()->student()->create(['avatar_url' => '/images/avatars/coach-taro.svg']);

        (new DestroyAvatarAction)($user);

        $this->assertNull($user->refresh()->avatar_url);
        $this->assertFileExists(public_path('images/avatars/coach-taro.svg'));
    }

    public function test_update_password_hashes_value(): void
    {
        $user = User::factory()->student()->create();

        (new UpdatePasswordAction)($user, 'brand-new-pass');

        $user->refresh();
        $this->assertNotSame('brand-new-pass', $user->password);
        $this->assertTrue(Hash::check('brand-new-pass', $user->password));
    }
}
