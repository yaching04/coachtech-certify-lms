<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_plan_list(): void
    {
        $admin = User::factory()->admin()->create();
        Plan::factory()->published()->count(3)->create();

        $response = $this->actingAs($admin)->get(route('admin.plans.index'));

        $response->assertOk();
        $response->assertViewIs('plan.management.index');
        $response->assertViewHas('plans');
    }

    public function test_coach_cannot_access(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)
            ->get(route('admin.plans.index'))
            ->assertForbidden();
    }

    public function test_student_cannot_access(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('admin.plans.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.plans.index'))
            ->assertRedirect('/login');
    }

    public function test_keyword_filter_matches_name(): void
    {
        $admin = User::factory()->admin()->create();
        Plan::factory()->create(['name' => 'Alpha Plan']);
        Plan::factory()->create(['name' => 'Beta Plan']);

        $response = $this->actingAs($admin)->get(route('admin.plans.index', ['keyword' => 'Alpha']));

        $response->assertOk();
        $response->assertSee('Alpha Plan');
        $response->assertDontSee('Beta Plan');
    }

    public function test_status_filter_returns_only_matching_status(): void
    {
        $admin = User::factory()->admin()->create();
        Plan::factory()->draft()->create(['name' => 'Draft One']);
        Plan::factory()->published()->create(['name' => 'Published One']);
        Plan::factory()->archived()->create(['name' => 'Archived One']);

        $response = $this->actingAs($admin)->get(route('admin.plans.index', ['status' => 'archived']));

        $response->assertOk();
        $response->assertSee('Archived One');
        $response->assertDontSee('Draft One');
        $response->assertDontSee('Published One');
    }

    public function test_sorted_by_sort_order_ascending(): void
    {
        $admin = User::factory()->admin()->create();
        Plan::factory()->create(['name' => 'Second Plan', 'sort_order' => 20]);
        Plan::factory()->create(['name' => 'First Plan', 'sort_order' => 10]);

        $response = $this->actingAs($admin)->get(route('admin.plans.index'));

        $response->assertOk();
        $response->assertSeeInOrder(['First Plan', 'Second Plan']);
    }

    public function test_paginates_20_per_page(): void
    {
        $admin = User::factory()->admin()->create();
        Plan::factory()->count(22)->create();

        $response = $this->actingAs($admin)->get(route('admin.plans.index'));

        $response->assertOk();
        $plans = $response->viewData('plans');
        $this->assertSame(20, $plans->perPage());
        $this->assertSame(22, $plans->total());
    }

    public function test_each_row_has_users_count(): void
    {
        $admin = User::factory()->admin()->create();
        $withUsers = Plan::factory()->published()->create(['name' => 'With Users']);
        $empty = Plan::factory()->published()->create(['name' => 'Empty Plan']);
        User::factory()->student()->count(2)->create(['plan_id' => $withUsers->id]);

        $response = $this->actingAs($admin)->get(route('admin.plans.index'));

        $response->assertOk();
        $plans = $response->viewData('plans')->getCollection()->keyBy('id');
        $this->assertSame(2, $plans[$withUsers->id]->users_count);
        $this->assertSame(0, $plans[$empty->id]->users_count);
    }
}
