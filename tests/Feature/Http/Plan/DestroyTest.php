<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_draft_plan_without_users(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan));

        $response->assertRedirect(route('admin.plans.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }

    public function test_cannot_delete_draft_plan_with_users(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();
        User::factory()->student()->create(['plan_id' => $plan->id]);

        $response = $this->actingAs($admin)->deleteJson(route('admin.plans.destroy', $plan));

        $response->assertStatus(409);
        $this->assertDatabaseHas('plans', ['id' => $plan->id]);
    }

    public function test_cannot_delete_published_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $response = $this->actingAs($admin)->deleteJson(route('admin.plans.destroy', $plan));

        $response->assertStatus(409);
        $response->assertJson(['message' => '下書き以外のプランは削除できません。公開中のプランはアーカイブしてください。']);
        $this->assertDatabaseHas('plans', ['id' => $plan->id]);
    }

    public function test_cannot_delete_archived_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->archived()->create();

        $this->actingAs($admin)->deleteJson(route('admin.plans.destroy', $plan))->assertStatus(409);

        $this->assertDatabaseHas('plans', ['id' => $plan->id]);
    }

    public function test_cannot_delete_plan_referenced_only_by_plan_log(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();
        UserPlanLog::factory()->create(['plan_id' => $plan->id]);

        $this->actingAs($admin)->deleteJson(route('admin.plans.destroy', $plan))->assertStatus(409);

        $this->assertDatabaseHas('plans', ['id' => $plan->id]);
    }

    public function test_blocked_delete_from_browser_redirects_back_with_error(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.plans.show', $plan))
            ->delete(route('admin.plans.destroy', $plan));

        $response->assertRedirect(route('admin.plans.show', $plan));
        $response->assertSessionHas('error', '下書き以外のプランは削除できません。公開中のプランはアーカイブしてください。');
        $this->assertDatabaseHas('plans', ['id' => $plan->id]);
    }

    public function test_coach_and_student_cannot_delete(): void
    {
        $plan = Plan::factory()->draft()->create();

        foreach ([User::factory()->coach()->create(), User::factory()->student()->create()] as $user) {
            $this->actingAs($user)
                ->delete(route('admin.plans.destroy', $plan))
                ->assertForbidden();
        }

        $this->assertDatabaseHas('plans', ['id' => $plan->id]);
    }
}
