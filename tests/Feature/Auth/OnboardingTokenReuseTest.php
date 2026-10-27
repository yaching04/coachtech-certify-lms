<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\Plan;
use App\Models\User;
use App\Services\InvitationTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * B-B-06: オンボーディング完了後の招待トークンは使用済みになり、再利用できないことを担保する。
 */
class OnboardingTokenReuseTest extends TestCase
{
    use RefreshDatabase;

    private function pendingInvitation(): Invitation
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create(['duration_days' => 90, 'default_meeting_quota' => 0]);
        $user = User::factory()->invited()->withPlan($plan)->create();

        return Invitation::factory()
            ->forUser($user)
            ->pending()
            ->create(['invited_by_user_id' => $admin->id]);
    }

    private function postUrl(Invitation $invitation): string
    {
        return URL::temporarySignedRoute(
            'onboarding.store',
            $invitation->expires_at,
            ['invitation' => $invitation->id],
        );
    }

    /**
     * @return array<string, string>
     */
    private function payload(string $name = '受講太郎', string $password = 'secret-pass'): array
    {
        return [
            'name' => $name,
            'password' => $password,
            'password_confirmation' => $password,
        ];
    }

    public function test_invitation_becomes_accepted_with_accepted_at_after_onboarding(): void
    {
        $invitation = $this->pendingInvitation();

        $this->post($this->postUrl($invitation), $this->payload())->assertRedirect(route('dashboard.index'));

        $invitation->refresh();
        $this->assertSame(InvitationStatus::Accepted, $invitation->status);
        $this->assertNotNull($invitation->accepted_at);
    }

    public function test_second_post_with_same_url_is_rejected_with_410_and_does_not_overwrite(): void
    {
        $invitation = $this->pendingInvitation();
        $url = $this->postUrl($invitation);

        $this->post($url, $this->payload('初回の名前', 'first-password'))->assertRedirect();
        $userName = $invitation->user->refresh()->name;
        $passwordHash = $invitation->user->password;
        Auth::logout();

        $this->post($url, $this->payload('上書きの名前', 'second-password'))->assertStatus(410);

        $user = $invitation->user->refresh();
        $this->assertSame($userName, $user->name);
        $this->assertSame('初回の名前', $user->name);
        $this->assertSame($passwordHash, $user->password);
        $this->assertGuest();
    }

    public function test_show_with_same_url_after_onboarding_renders_invalid_view(): void
    {
        $invitation = $this->pendingInvitation();
        $showUrl = app(InvitationTokenService::class)->generateUrl($invitation);

        $this->post($this->postUrl($invitation), $this->payload())->assertRedirect();
        Auth::logout();

        $this->get($showUrl)->assertOk()->assertViewIs('auth.invitation-invalid');
    }
}
