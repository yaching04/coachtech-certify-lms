<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_archives_published_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $response = $this->actingAs($admin)->post(route('admin.plans.archive', $plan));

        $response->assertRedirect(route('admin.plans.show', $plan));
        $response->assertSessionHas('success');
        $this->assertSame('archived', $plan->fresh()->status->value);
        $this->assertSame($admin->id, $plan->fresh()->updated_by_user_id);
    }

    #[DataProvider('invalidFromStatuses')]
    public function test_cannot_archive_from_invalid_status(string $status): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->{$status}()->create();

        $response = $this->actingAs($admin)->postJson(route('admin.plans.archive', $plan));

        $response->assertStatus(409);
        $response->assertJson(['message' => '公開中のプランのみアーカイブできます。']);
        $this->assertSame($status, $plan->fresh()->status->value);
    }

    public function test_coach_and_student_cannot_archive(): void
    {
        $plan = Plan::factory()->published()->create();

        foreach ([User::factory()->coach()->create(), User::factory()->student()->create()] as $user) {
            $this->actingAs($user)
                ->post(route('admin.plans.archive', $plan))
                ->assertForbidden();
        }

        $this->assertSame('published', $plan->fresh()->status->value);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidFromStatuses(): array
    {
        return [
            'draft からは不可' => ['draft'],
            'archived からは不可' => ['archived'],
        ];
    }
}
