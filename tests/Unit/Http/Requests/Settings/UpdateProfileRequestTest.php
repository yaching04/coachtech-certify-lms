<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Settings;

use App\Http\Requests\Settings\StoreAvatarRequest;
use App\Http\Requests\Settings\UpdatePasswordRequest;
use App\Http\Requests\Settings\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 設定系 FormRequest の rules / authorize の検証(ロール別の meeting_url ルール、エラーバッグ名、未ログイン時の拒否)。
 */
class UpdateProfileRequestTest extends TestCase
{
    use RefreshDatabase;

    private function requestFor(string $class, ?User $user): UpdateProfileRequest|StoreAvatarRequest|UpdatePasswordRequest
    {
        $request = $class::create('/settings', 'POST');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    public function test_coach_rules_include_meeting_url(): void
    {
        $rules = $this->requestFor(UpdateProfileRequest::class, User::factory()->coach()->create())->rules();

        $this->assertArrayHasKey('meeting_url', $rules);
        $this->assertContains('required', $rules['meeting_url']);
    }

    public function test_student_and_admin_rules_exclude_meeting_url(): void
    {
        foreach ([User::factory()->student()->create(), User::factory()->admin()->create()] as $user) {
            $rules = $this->requestFor(UpdateProfileRequest::class, $user)->rules();

            $this->assertArrayNotHasKey('meeting_url', $rules);
            $this->assertArrayNotHasKey('email', $rules);
            $this->assertArrayNotHasKey('role', $rules);
        }
    }

    public function test_authorize_requires_login(): void
    {
        foreach ([UpdateProfileRequest::class, StoreAvatarRequest::class, UpdatePasswordRequest::class] as $class) {
            $this->assertFalse($this->requestFor($class, null)->authorize());
            $this->assertTrue($this->requestFor($class, User::factory()->student()->create())->authorize());
        }
    }

    public function test_password_request_uses_update_password_error_bag(): void
    {
        $request = $this->requestFor(UpdatePasswordRequest::class, User::factory()->student()->create());

        $reflection = new \ReflectionProperty($request, 'errorBag');

        $this->assertSame('updatePassword', $reflection->getValue($request));
    }
}
