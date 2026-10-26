<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * パスワード変更の検証(現在のパスワード確認 / 新パスワードのルール / 卒業済みでも変更可)。
 */
class PasswordTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function allRoleUsers(): array
    {
        return ['admin' => ['admin'], 'coach' => ['coach'], 'student' => ['student'], 'graduated' => ['graduated']];
    }

    private function makeUser(string $kind): User
    {
        return match ($kind) {
            'admin' => User::factory()->admin()->create(),
            'coach' => User::factory()->coach()->create(),
            'graduated' => User::factory()->student()->graduated()->create(),
            default => User::factory()->student()->create(),
        };
    }

    #[DataProvider('allRoleUsers')]
    public function test_every_role_can_change_password(string $kind): void
    {
        $user = $this->makeUser($kind);

        $this->actingAs($user)
            ->put(route('settings.password.update'), [
                'current_password' => 'password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect(route('settings.profile.edit', ['tab' => 'password']))
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('new-password-123', $user->refresh()->password));
    }

    public function test_user_stays_logged_in_after_change(): void
    {
        $user = $this->makeUser('student');

        $this->actingAs($user)->put(route('settings.password.update'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $this->get(route('settings.profile.edit'))->assertOk();
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = $this->makeUser('student');

        $this->actingAs($user)
            ->put(route('settings.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertSessionHasErrorsIn('updatePassword', 'current_password');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    #[DataProvider('invalidNewPasswords')]
    public function test_invalid_new_password_is_rejected(array $payload, string $errorField): void
    {
        $user = $this->makeUser('student');

        $this->actingAs($user)
            ->put(route('settings.password.update'), ['current_password' => 'password'] + $payload)
            ->assertSessionHasErrorsIn('updatePassword', $errorField);

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string}>
     */
    public static function invalidNewPasswords(): array
    {
        return [
            'too short' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
            'mismatch' => [['password' => 'new-password-123', 'password_confirmation' => 'other-password'], 'password'],
            'missing confirmation' => [['password' => 'new-password-123'], 'password'],
            'empty' => [['password' => '', 'password_confirmation' => ''], 'password'],
        ];
    }

    public function test_current_password_is_required(): void
    {
        $user = $this->makeUser('student');

        $this->actingAs($user)
            ->put(route('settings.password.update'), ['password' => 'new-password-123', 'password_confirmation' => 'new-password-123'])
            ->assertSessionHasErrorsIn('updatePassword', 'current_password');
    }

    public function test_errors_are_displayed_on_password_tab(): void
    {
        $user = $this->makeUser('student');

        $this->actingAs($user)
            ->from(route('settings.profile.edit', ['tab' => 'password']))
            ->put(route('settings.password.update'), [
                'current_password' => 'wrong',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]);

        $this->actingAs($user)
            ->followingRedirects()
            ->from(route('settings.profile.edit', ['tab' => 'password']))
            ->put(route('settings.password.update'), [
                'current_password' => 'wrong',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertSee('現在のパスワードが正しくありません。');
    }
}
