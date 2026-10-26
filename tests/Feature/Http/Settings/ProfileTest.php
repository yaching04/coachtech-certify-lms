<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 設定・プロフィール画面の表示 / 更新(氏名・自己紹介・コーチの固定面談 URL)の検証。
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function allRoleUsers(): array
    {
        return [
            'admin' => ['admin'],
            'coach' => ['coach'],
            'student' => ['student'],
            'graduated student' => ['graduated'],
        ];
    }

    private function makeUser(string $kind): User
    {
        return match ($kind) {
            'admin' => User::factory()->admin()->create(),
            'coach' => User::factory()->coach()->create(['meeting_url' => 'https://meet.example.com/old']),
            'graduated' => User::factory()->student()->graduated()->create(),
            default => User::factory()->student()->inProgress()->create(),
        };
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('settings.profile.edit'))->assertRedirect(route('login'));
        $this->patch(route('settings.profile.update'), ['name' => 'x'])->assertRedirect(route('login'));
        $this->post(route('settings.avatar.store'))->assertRedirect(route('login'));
        $this->delete(route('settings.avatar.destroy'))->assertRedirect(route('login'));
        $this->put(route('settings.password.update'), [])->assertRedirect(route('login'));
    }

    #[DataProvider('allRoleUsers')]
    public function test_every_role_can_view_profile_page(string $kind): void
    {
        $user = $this->makeUser($kind);

        $this->actingAs($user)
            ->get(route('settings.profile.edit'))
            ->assertOk()
            ->assertSee($user->email)
            ->assertSee('name="name"', false);
    }

    public function test_email_is_shown_read_only(): void
    {
        $user = User::factory()->student()->create();

        $this->actingAs($user)
            ->get(route('settings.profile.edit'))
            ->assertOk()
            ->assertSee('readonly', false);
    }

    public function test_meeting_url_field_is_only_shown_to_coach(): void
    {
        $this->actingAs($this->makeUser('coach'))
            ->get(route('settings.profile.edit'))
            ->assertSee('name="meeting_url"', false);

        $this->actingAs($this->makeUser('student'))
            ->get(route('settings.profile.edit'))
            ->assertDontSee('name="meeting_url"', false);

        $this->actingAs($this->makeUser('admin'))
            ->get(route('settings.profile.edit'))
            ->assertDontSee('name="meeting_url"', false);
    }

    public function test_password_tab_is_rendered(): void
    {
        $this->actingAs($this->makeUser('student'))
            ->get(route('settings.profile.edit', ['tab' => 'password']))
            ->assertOk()
            ->assertSee('name="current_password"', false);
    }

    #[DataProvider('allRoleUsers')]
    public function test_every_role_can_update_name_and_bio(string $kind): void
    {
        $user = $this->makeUser($kind);
        $payload = ['name' => '新しい名前', 'bio' => '新しい自己紹介'];
        if ($user->role === UserRole::Coach) {
            $payload['meeting_url'] = 'https://meet.example.com/new';
        }

        $this->actingAs($user)
            ->patch(route('settings.profile.update'), $payload)
            ->assertRedirect(route('settings.profile.edit'))
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('新しい名前', $user->name);
        $this->assertSame('新しい自己紹介', $user->bio);
    }

    public function test_bio_can_be_cleared(): void
    {
        $user = User::factory()->student()->create(['bio' => '古い']);

        $this->actingAs($user)
            ->patch(route('settings.profile.update'), ['name' => $user->name, 'bio' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNull($user->refresh()->bio);
    }

    public function test_email_role_and_status_are_ignored(): void
    {
        $user = User::factory()->student()->inProgress()->create(['email' => 'keep@example.com']);

        $this->actingAs($user)
            ->patch(route('settings.profile.update'), [
                'name' => '変更後',
                'email' => 'hacked@example.com',
                'role' => 'admin',
                'status' => 'graduated',
                'avatar_url' => 'https://evil.example.com/a.png',
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('変更後', $user->name);
        $this->assertSame('keep@example.com', $user->email);
        $this->assertSame(UserRole::Student, $user->role);
        $this->assertSame('in_progress', $user->status->value);
        $this->assertNull($user->avatar_url);
    }

    public function test_update_only_affects_logged_in_user(): void
    {
        $me = User::factory()->student()->create();
        $other = User::factory()->student()->create(['name' => '他人']);

        $this->actingAs($me)->patch(route('settings.profile.update'), ['name' => '自分', 'id' => $other->id]);

        $this->assertSame('他人', $other->refresh()->name);
        $this->assertSame('自分', $me->refresh()->name);
    }

    public function test_coach_can_update_meeting_url(): void
    {
        $coach = $this->makeUser('coach');

        $this->actingAs($coach)
            ->patch(route('settings.profile.update'), [
                'name' => $coach->name,
                'meeting_url' => 'https://meet.example.com/new-room',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('https://meet.example.com/new-room', $coach->refresh()->meeting_url);
    }

    #[DataProvider('invalidMeetingUrls')]
    public function test_coach_meeting_url_is_validated(?string $value): void
    {
        $coach = $this->makeUser('coach');

        $this->actingAs($coach)
            ->from(route('settings.profile.edit'))
            ->patch(route('settings.profile.update'), ['name' => $coach->name, 'meeting_url' => $value])
            ->assertRedirect(route('settings.profile.edit'))
            ->assertSessionHasErrors('meeting_url');

        $this->assertSame('https://meet.example.com/old', $coach->refresh()->meeting_url);
    }

    /**
     * @return array<string, array{0: ?string}>
     */
    public static function invalidMeetingUrls(): array
    {
        return [
            'empty' => [''],
            'null' => [null],
            'not url' => ['not-a-url'],
            'too long' => ['https://example.com/'.str_repeat('a', 500)],
        ];
    }

    public function test_student_cannot_set_meeting_url(): void
    {
        $student = $this->makeUser('student');

        $this->actingAs($student)
            ->patch(route('settings.profile.update'), ['name' => $student->name, 'meeting_url' => 'https://meet.example.com/x'])
            ->assertSessionHasNoErrors();

        $this->assertNull($student->refresh()->meeting_url);
    }

    public function test_name_is_required_and_limited_to_50_chars(): void
    {
        $user = $this->makeUser('student');
        $original = $user->name;

        $this->actingAs($user)->patch(route('settings.profile.update'), ['name' => ''])
            ->assertSessionHasErrors('name');
        $this->actingAs($user)->patch(route('settings.profile.update'), ['name' => str_repeat('あ', 51)])
            ->assertSessionHasErrors('name');
        $this->actingAs($user)->patch(route('settings.profile.update'), ['name' => str_repeat('あ', 50)])
            ->assertSessionHasNoErrors();

        $this->assertSame(str_repeat('あ', 50), $user->refresh()->name);
        $this->assertNotSame($original, $user->name);
    }

    public function test_bio_is_limited_to_1000_chars(): void
    {
        $user = $this->makeUser('student');

        $this->actingAs($user)->patch(route('settings.profile.update'), ['name' => 'a', 'bio' => str_repeat('あ', 1001)])
            ->assertSessionHasErrors('bio');
        $this->actingAs($user)->patch(route('settings.profile.update'), ['name' => 'a', 'bio' => str_repeat('あ', 1000)])
            ->assertSessionHasNoErrors();
    }

    public function test_graduated_student_is_not_locked_out(): void
    {
        $user = $this->makeUser('graduated');

        $this->actingAs($user)->get(route('settings.profile.edit'))->assertOk();
        $this->actingAs($user)->patch(route('settings.profile.update'), ['name' => '卒業後も変更可'])
            ->assertSessionHasNoErrors();
        $this->assertSame('卒業後も変更可', $user->refresh()->name);
    }
}
