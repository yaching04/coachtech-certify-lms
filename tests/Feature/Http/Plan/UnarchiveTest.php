<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UnarchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_unarchives_archived_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->archived()->create();

        $response = $this->actingAs($admin)->post(route('admin.plans.unarchive', $plan));

        $response->assertRedirect(route('admin.plans.show', $plan));
        $response->assertSessionHas('success');
        $this->assertSame('draft', $plan->fresh()->status->value);
        $this->assertSame($admin->id, $plan->fresh()->updated_by_user_id);
    }

    #[DataProvider('invalidFromStatuses')]
    public function test_cannot_unarchive_from_invalid_status(string $status): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->{$status}()->create();

        $response = $this->actingAs($admin)->postJson(route('admin.plans.unarchive', $plan));

        $response->assertStatus(409);
        $response->assertJson(['message' => 'アーカイブ済みのプランのみ下書きへ戻せます。']);
        $this->assertSame($status, $plan->fresh()->status->value);
    }

    public function test_coach_and_student_cannot_unarchive(): void
    {
        $plan = Plan::factory()->archived()->create();

        foreach ([User::factory()->coach()->create(), User::factory()->student()->create()] as $user) {
            $this->actingAs($user)
                ->post(route('admin.plans.unarchive', $plan))
                ->assertForbidden();
        }

        $this->assertSame('archived', $plan->fresh()->status->value);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidFromStatuses(): array
    {
        return [
            'draft からは不可' => ['draft'],
            'published からは不可' => ['published'],
        ];
    }
}
