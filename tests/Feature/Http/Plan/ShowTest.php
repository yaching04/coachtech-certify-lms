<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_plan_detail(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Creator Admin']);
        $plan = Plan::factory()->draft()->create([
            'name' => 'Detail Plan',
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.plans.show', $plan));

        $response->assertOk();
        $response->assertViewIs('plan.management.show');
        $response->assertSee('Detail Plan');
        $response->assertSee('Creator Admin');
    }

    public function test_draft_shows_publish_and_delete_buttons(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($admin)->get(route('admin.plans.show', $plan));

        $response->assertSee(route('admin.plans.publish', $plan));
        $response->assertDontSee(route('admin.plans.archive', $plan));
        $response->assertDontSee(route('admin.plans.unarchive', $plan));
        $response->assertSee('onsubmit="return confirm', false);
    }

    public function test_published_shows_archive_button(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $response = $this->actingAs($admin)->get(route('admin.plans.show', $plan));

        $response->assertSee(route('admin.plans.archive', $plan));
        $response->assertDontSee(route('admin.plans.publish', $plan));
        $response->assertDontSee(route('admin.plans.unarchive', $plan));
    }

    public function test_archived_shows_unarchive_button(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->archived()->create();

        $response = $this->actingAs($admin)->get(route('admin.plans.show', $plan));

        $response->assertSee(route('admin.plans.unarchive', $plan));
        $response->assertDontSee(route('admin.plans.publish', $plan));
        $response->assertDontSee(route('admin.plans.archive', $plan));
    }

    public function test_lists_users_assigned_to_the_plan_only(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();
        $other = Plan::factory()->published()->create();
        User::factory()->student()->create(['email' => 'in-plan@example.test', 'plan_id' => $plan->id]);
        User::factory()->student()->create(['email' => 'other-plan@example.test', 'plan_id' => $other->id]);

        $response = $this->actingAs($admin)->get(route('admin.plans.show', $plan));

        $response->assertOk();
        $response->assertSee('in-plan@example.test');
        $response->assertDontSee('other-plan@example.test');
    }

    public function test_returns_404_for_unknown_id(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.plans.show', '01J0000000000000000000ZZZZ'))
            ->assertNotFound();
    }

    public function test_coach_and_student_cannot_view_detail(): void
    {
        $plan = Plan::factory()->published()->create();

        foreach ([User::factory()->coach()->create(), User::factory()->student()->create()] as $user) {
            $this->actingAs($user)
                ->get(route('admin.plans.show', $plan))
                ->assertForbidden();
        }
    }
}
