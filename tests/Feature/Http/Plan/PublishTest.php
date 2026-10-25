<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_publishes_draft_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($admin)->post(route('admin.plans.publish', $plan));

        $response->assertRedirect(route('admin.plans.show', $plan));
        $response->assertSessionHas('success');
        $this->assertSame('published', $plan->fresh()->status->value);
        $this->assertSame($admin->id, $plan->fresh()->updated_by_user_id);
    }

    #[DataProvider('invalidFromStatuses')]
    public function test_cannot_publish_from_invalid_status(string $status): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->{$status}()->create();

        $response = $this->actingAs($admin)->postJson(route('admin.plans.publish', $plan));

        $response->assertStatus(409);
        $response->assertJson(['message' => '下書き状態のプランのみ公開できます。']);
        $this->assertSame($status, $plan->fresh()->status->value);
    }

    public function test_coach_and_student_cannot_publish(): void
    {
        $plan = Plan::factory()->draft()->create();

        foreach ([User::factory()->coach()->create(), User::factory()->student()->create()] as $user) {
            $this->actingAs($user)
                ->post(route('admin.plans.publish', $plan))
                ->assertForbidden();
        }

        $this->assertSame('draft', $plan->fresh()->status->value);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidFromStatuses(): array
    {
        return [
            'published からは不可' => ['published'],
            'archived からは不可' => ['archived'],
        ];
    }
}
