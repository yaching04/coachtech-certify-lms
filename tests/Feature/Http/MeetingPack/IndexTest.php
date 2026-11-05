<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_meeting_pack_list(): void
    {
        $admin = User::factory()->admin()->create();
        MeetingPack::factory()->published()->count(3)->create();

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index'));

        $response->assertOk();
        $response->assertViewIs('meeting-pack.management.index');
        $response->assertViewHas('plans');
    }

    public function test_coach_cannot_access(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)
            ->get(route('admin.meeting-packs.index'))
            ->assertForbidden();
    }

    public function test_student_cannot_access(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('admin.meeting-packs.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.meeting-packs.index'))
            ->assertRedirect('/login');
    }

    public function test_keyword_filter_matches_name(): void
    {
        $admin = User::factory()->admin()->create();
        MeetingPack::factory()->create(['name' => 'Alpha Pack']);
        MeetingPack::factory()->create(['name' => 'Beta Pack']);

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index', ['keyword' => 'Alpha']));

        $response->assertOk();
        $response->assertSee('Alpha Pack');
        $response->assertDontSee('Beta Pack');
    }

    public function test_status_filter_returns_only_matching_status(): void
    {
        $admin = User::factory()->admin()->create();
        MeetingPack::factory()->draft()->create(['name' => 'Draft One']);
        MeetingPack::factory()->published()->create(['name' => 'Published One']);
        MeetingPack::factory()->archived()->create(['name' => 'Archived One']);

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index', ['status' => 'archived']));

        $response->assertOk();
        $response->assertSee('Archived One');
        $response->assertDontSee('Draft One');
        $response->assertDontSee('Published One');
    }

    public function test_sorted_by_sort_order_ascending(): void
    {
        $admin = User::factory()->admin()->create();
        MeetingPack::factory()->create(['name' => 'Second Pack', 'sort_order' => 20]);
        MeetingPack::factory()->create(['name' => 'First Pack', 'sort_order' => 10]);

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index'));

        $response->assertOk();
        $response->assertSeeInOrder(['First Pack', 'Second Pack']);
    }

    public function test_paginates_20_per_page(): void
    {
        $admin = User::factory()->admin()->create();
        MeetingPack::factory()->count(22)->create();

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index'));

        $response->assertOk();
        $plans = $response->viewData('plans');
        $this->assertSame(20, $plans->perPage());
        $this->assertSame(22, $plans->total());
    }
}
