<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * アイコン画像(アバター)のアップロード / 差し替え / 削除の検証。
 */
class AvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function allowedExtensions(): array
    {
        return ['png' => ['png'], 'jpg' => ['jpg'], 'jpeg' => ['jpeg'], 'webp' => ['webp']];
    }

    #[DataProvider('allowedExtensions')]
    public function test_user_can_upload_avatar(string $ext): void
    {
        $user = User::factory()->student()->create();

        $this->actingAs($user)
            ->post(route('settings.avatar.store'), ['avatar' => UploadedFile::fake()->image("me.{$ext}", 200, 200)])
            ->assertRedirect(route('settings.profile.edit'))
            ->assertSessionHas('success')
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNotNull($user->avatar_url);
        $this->assertStringContainsString("avatars/{$user->id}/", $user->avatar_url);
        $this->assertCount(1, Storage::disk('public')->files("avatars/{$user->id}"));
    }

    public function test_graduated_student_and_coach_and_admin_can_upload(): void
    {
        foreach ([
            User::factory()->student()->graduated()->create(),
            User::factory()->coach()->create(),
            User::factory()->admin()->create(),
        ] as $user) {
            $this->actingAs($user)
                ->post(route('settings.avatar.store'), ['avatar' => UploadedFile::fake()->image('a.png')])
                ->assertSessionHasNoErrors();

            $this->assertNotNull($user->refresh()->avatar_url);
        }
    }

    public function test_replacing_avatar_removes_the_old_file(): void
    {
        $user = User::factory()->student()->create();

        $this->actingAs($user)->post(route('settings.avatar.store'), ['avatar' => UploadedFile::fake()->image('a.png')]);
        $first = Storage::disk('public')->files("avatars/{$user->id}");
        $this->actingAs($user)->post(route('settings.avatar.store'), ['avatar' => UploadedFile::fake()->image('b.jpg')]);
        $second = Storage::disk('public')->files("avatars/{$user->id}");

        $this->assertCount(1, $first);
        $this->assertCount(1, $second);
        $this->assertNotSame($first[0], $second[0]);
    }

    public function test_avatar_is_required(): void
    {
        $user = User::factory()->student()->create();

        $this->actingAs($user)->post(route('settings.avatar.store'), [])->assertSessionHasErrors('avatar');
        $this->assertNull($user->refresh()->avatar_url);
    }

    public function test_invalid_format_is_rejected(): void
    {
        $user = User::factory()->student()->create();

        foreach ([
            UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->create('anim.gif', 10, 'image/gif'),
            UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
            UploadedFile::fake()->create('script.php', 1, 'application/x-php'),
        ] as $file) {
            $this->actingAs($user)->post(route('settings.avatar.store'), ['avatar' => $file])->assertSessionHasErrors('avatar');
        }

        $this->assertNull($user->refresh()->avatar_url);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_size_limit_is_2mb(): void
    {
        $user = User::factory()->student()->create();

        $this->actingAs($user)
            ->post(route('settings.avatar.store'), ['avatar' => UploadedFile::fake()->image('big.png')->size(2049)])
            ->assertSessionHasErrors('avatar');
        $this->assertNull($user->refresh()->avatar_url);

        $this->actingAs($user)
            ->post(route('settings.avatar.store'), ['avatar' => UploadedFile::fake()->image('ok.png')->size(2048)])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($user->refresh()->avatar_url);
    }

    public function test_user_can_delete_avatar(): void
    {
        $user = User::factory()->student()->create();
        $this->actingAs($user)->post(route('settings.avatar.store'), ['avatar' => UploadedFile::fake()->image('a.png')]);

        $this->actingAs($user)
            ->delete(route('settings.avatar.destroy'))
            ->assertRedirect(route('settings.profile.edit'))
            ->assertSessionHas('success');

        $this->assertNull($user->refresh()->avatar_url);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_delete_without_avatar_is_idempotent(): void
    {
        $user = User::factory()->student()->create();

        $this->actingAs($user)->delete(route('settings.avatar.destroy'))->assertRedirect(route('settings.profile.edit'));

        $this->assertNull($user->refresh()->avatar_url);
    }

    public function test_delete_does_not_touch_other_users_files(): void
    {
        $me = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $this->actingAs($other)->post(route('settings.avatar.store'), ['avatar' => UploadedFile::fake()->image('o.png')]);
        $this->actingAs($me)->post(route('settings.avatar.store'), ['avatar' => UploadedFile::fake()->image('m.png')]);

        $this->actingAs($me)->delete(route('settings.avatar.destroy'));

        $this->assertNotNull($other->refresh()->avatar_url);
        $this->assertCount(1, Storage::disk('public')->files("avatars/{$other->id}"));
    }

    public function test_delete_button_is_shown_only_when_avatar_exists(): void
    {
        $user = User::factory()->student()->create();

        $this->actingAs($user)->get(route('settings.profile.edit'))
            ->assertDontSee('アイコン画像を削除します', false);

        $this->actingAs($user)->post(route('settings.avatar.store'), ['avatar' => UploadedFile::fake()->image('a.png')]);

        $this->actingAs($user)->get(route('settings.profile.edit'))
            ->assertSee('アイコン画像を削除します', false);
    }
}
