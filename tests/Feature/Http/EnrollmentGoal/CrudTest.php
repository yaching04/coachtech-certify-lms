<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 個人目標(EnrollmentGoal)の追加 / 編集 / 削除 / 達成マーク / 達成解除と、受講登録詳細での表示・認可の検証。
 */
class CrudTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        $this->enrollment = Enrollment::factory()->for($this->student)->for($cert)->learning()->create();
    }

    private function assignCoach(User $coach): void
    {
        $this->enrollment->certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
        ]);
    }

    private function goal(array $attributes = []): EnrollmentGoal
    {
        return EnrollmentGoal::factory()->forEnrollment($this->enrollment)->create($attributes);
    }

    // ---- store ----

    public function test_student_can_add_goal_to_own_enrollment(): void
    {
        $response = $this->actingAs($this->student)
            ->post(route('enrollments.goals.store', $this->enrollment), [
                'title' => '過去問を解く',
                'description' => '5 年分',
                'target_date' => '2030-01-31',
            ]);

        $response->assertRedirect(route('enrollments.show', $this->enrollment));
        $response->assertSessionHas('success');
        $goal = EnrollmentGoal::firstOrFail();
        $this->assertSame($this->enrollment->id, $goal->enrollment_id);
        $this->assertSame('過去問を解く', $goal->title);
        $this->assertSame('5 年分', $goal->description);
        $this->assertSame('2030-01-31', $goal->target_date->toDateString());
        $this->assertNull($goal->achieved_at);
    }

    public function test_student_can_add_goal_with_title_only(): void
    {
        $this->actingAs($this->student)
            ->post(route('enrollments.goals.store', $this->enrollment), ['title' => '毎日学習する'])
            ->assertRedirect(route('enrollments.show', $this->enrollment));

        $this->assertDatabaseHas('enrollment_goals', [
            'enrollment_id' => $this->enrollment->id,
            'title' => '毎日学習する',
            'description' => null,
            'target_date' => null,
        ]);
    }

    public function test_store_rejects_invalid_input(): void
    {
        $response = $this->actingAs($this->student)
            ->from(route('enrollments.show', $this->enrollment))
            ->post(route('enrollments.goals.store', $this->enrollment), [
                'title' => '',
                'target_date' => 'not-a-date',
            ]);

        $response->assertRedirect(route('enrollments.show', $this->enrollment));
        $response->assertSessionHasErrors(['title', 'target_date']);
        $this->assertDatabaseCount('enrollment_goals', 0);
    }

    public function test_other_student_cannot_add_goal_to_someone_elses_enrollment(): void
    {
        $other = User::factory()->student()->inProgress()->create();

        $this->actingAs($other)
            ->postJson(route('enrollments.goals.store', $this->enrollment), ['title' => '乗っ取り'])
            ->assertForbidden();

        $this->assertDatabaseCount('enrollment_goals', 0);
    }

    public function test_coach_and_admin_cannot_add_goal(): void
    {
        $coach = User::factory()->coach()->create();
        $this->assignCoach($coach);
        $admin = User::factory()->admin()->create();

        $this->actingAs($coach)
            ->postJson(route('enrollments.goals.store', $this->enrollment), ['title' => 'x'])
            ->assertForbidden();
        $this->actingAs($admin)
            ->postJson(route('enrollments.goals.store', $this->enrollment), ['title' => 'x'])
            ->assertForbidden();

        $this->assertDatabaseCount('enrollment_goals', 0);
    }

    public function test_guest_is_redirected_to_login_on_store(): void
    {
        $this->post(route('enrollments.goals.store', $this->enrollment), ['title' => 'x'])
            ->assertRedirect(route('login'));
    }

    public function test_can_add_goal_even_when_enrollment_is_passed(): void
    {
        $passed = Enrollment::factory()->for($this->student)->passed()->create();

        $this->actingAs($this->student)
            ->post(route('enrollments.goals.store', $passed), ['title' => '合格後の振り返り'])
            ->assertRedirect(route('enrollments.show', $passed));

        $this->assertDatabaseHas('enrollment_goals', ['enrollment_id' => $passed->id, 'title' => '合格後の振り返り']);
    }

    // ---- edit / update ----

    public function test_student_can_open_edit_page_and_update_own_goal(): void
    {
        $goal = $this->goal(['title' => '旧タイトル']);

        $this->actingAs($this->student)
            ->get(route('enrollment-goals.edit', $goal))
            ->assertOk()
            ->assertSee('旧タイトル');

        $response = $this->actingAs($this->student)
            ->patch(route('enrollment-goals.update', $goal), [
                'title' => '新タイトル',
                'description' => '',
                'target_date' => '',
            ]);

        $response->assertRedirect(route('enrollments.show', $this->enrollment));
        $response->assertSessionHas('success');
        $goal->refresh();
        $this->assertSame('新タイトル', $goal->title);
        $this->assertNull($goal->description);
        $this->assertNull($goal->target_date);
    }

    public function test_update_does_not_change_achieved_state(): void
    {
        $goal = $this->goal(['achieved_at' => now()->subDay()]);

        $this->actingAs($this->student)
            ->patch(route('enrollment-goals.update', $goal), ['title' => '達成済のまま編集'])
            ->assertRedirect();

        $this->assertNotNull($goal->refresh()->achieved_at);
    }

    public function test_update_rejects_too_long_title(): void
    {
        $goal = $this->goal(['title' => '元']);

        $this->actingAs($this->student)
            ->patchJson(route('enrollment-goals.update', $goal), ['title' => str_repeat('あ', 101)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');

        $this->assertSame('元', $goal->refresh()->title);
    }

    public function test_other_student_cannot_edit_or_update_goal(): void
    {
        $goal = $this->goal(['title' => '元']);
        $other = User::factory()->student()->inProgress()->create();

        $this->actingAs($other)->get(route('enrollment-goals.edit', $goal))->assertForbidden();
        $this->actingAs($other)
            ->patchJson(route('enrollment-goals.update', $goal), ['title' => '改ざん'])
            ->assertForbidden();

        $this->assertSame('元', $goal->refresh()->title);
    }

    public function test_coach_and_admin_cannot_edit_or_update_goal(): void
    {
        $goal = $this->goal();
        $coach = User::factory()->coach()->create();
        $this->assignCoach($coach);
        $admin = User::factory()->admin()->create();

        foreach ([$coach, $admin] as $actor) {
            $this->actingAs($actor)->get(route('enrollment-goals.edit', $goal))->assertForbidden();
            $this->actingAs($actor)
                ->patchJson(route('enrollment-goals.update', $goal), ['title' => 'x'])
                ->assertForbidden();
        }
    }

    // ---- destroy ----

    public function test_student_can_delete_own_goal_including_achieved_one(): void
    {
        $open = $this->goal();
        $done = $this->goal(['achieved_at' => now()]);

        foreach ([$open, $done] as $goal) {
            $this->actingAs($this->student)
                ->delete(route('enrollment-goals.destroy', $goal))
                ->assertRedirect(route('enrollments.show', $this->enrollment))
                ->assertSessionHas('success');
        }

        $this->assertDatabaseCount('enrollment_goals', 0);
    }

    public function test_other_student_coach_admin_cannot_delete_goal(): void
    {
        $goal = $this->goal();
        $other = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $this->assignCoach($coach);
        $admin = User::factory()->admin()->create();

        foreach ([$other, $coach, $admin] as $actor) {
            $this->actingAs($actor)
                ->deleteJson(route('enrollment-goals.destroy', $goal))
                ->assertForbidden();
        }

        $this->assertDatabaseCount('enrollment_goals', 1);
    }

    // ---- achieve / unachieve ----

    public function test_student_can_mark_goal_achieved_and_unmark(): void
    {
        $goal = $this->goal();

        $this->actingAs($this->student)
            ->post(route('enrollment-goals.markAchieved', $goal))
            ->assertRedirect(route('enrollments.show', $this->enrollment))
            ->assertSessionHas('success');
        $this->assertNotNull($goal->refresh()->achieved_at);

        $this->actingAs($this->student)
            ->delete(route('enrollment-goals.unmarkAchieved', $goal))
            ->assertRedirect(route('enrollments.show', $this->enrollment))
            ->assertSessionHas('success');
        $this->assertNull($goal->refresh()->achieved_at);
    }

    public function test_mark_achieved_twice_returns_conflict_for_json(): void
    {
        $goal = $this->goal(['achieved_at' => now()]);

        $this->actingAs($this->student)
            ->postJson(route('enrollment-goals.markAchieved', $goal))
            ->assertStatus(409);
    }

    public function test_unmark_when_not_achieved_returns_conflict_for_json(): void
    {
        $goal = $this->goal();

        $this->actingAs($this->student)
            ->deleteJson(route('enrollment-goals.unmarkAchieved', $goal))
            ->assertStatus(409);
    }

    public function test_mark_achieved_twice_redirects_back_with_error_for_html(): void
    {
        $goal = $this->goal(['achieved_at' => now()]);

        $this->actingAs($this->student)
            ->from(route('enrollments.show', $this->enrollment))
            ->post(route('enrollment-goals.markAchieved', $goal))
            ->assertRedirect(route('enrollments.show', $this->enrollment))
            ->assertSessionHas('error');
    }

    public function test_other_student_coach_admin_cannot_change_achieved_state(): void
    {
        $goal = $this->goal();
        $done = $this->goal(['achieved_at' => now()]);
        $other = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $this->assignCoach($coach);
        $admin = User::factory()->admin()->create();

        foreach ([$other, $coach, $admin] as $actor) {
            $this->actingAs($actor)->postJson(route('enrollment-goals.markAchieved', $goal))->assertForbidden();
            $this->actingAs($actor)->deleteJson(route('enrollment-goals.unmarkAchieved', $done))->assertForbidden();
        }

        $this->assertNull($goal->refresh()->achieved_at);
        $this->assertNotNull($done->refresh()->achieved_at);
    }

    // ---- 受講登録詳細での表示 / 連動削除 ----

    public function test_owner_sees_goals_with_forms_on_enrollment_show(): void
    {
        $this->goal(['title' => '目標A']);

        $this->actingAs($this->student)
            ->get(route('enrollments.show', $this->enrollment))
            ->assertOk()
            ->assertSee('個人目標')
            ->assertSee('目標A')
            ->assertSee(route('enrollments.goals.store', $this->enrollment), false);
    }

    public function test_assigned_coach_and_admin_can_view_goals_read_only(): void
    {
        $this->goal(['title' => '目標B']);
        $coach = User::factory()->coach()->create();
        $this->assignCoach($coach);
        $admin = User::factory()->admin()->create();

        foreach ([$coach, $admin] as $viewer) {
            $this->actingAs($viewer)
                ->get(route('enrollments.show', $this->enrollment))
                ->assertOk()
                ->assertSee('目標B')
                ->assertDontSee(route('enrollments.goals.store', $this->enrollment), false);
        }
    }

    public function test_unassigned_coach_and_other_student_cannot_view_goals(): void
    {
        $this->goal(['title' => '目標C']);
        $coach = User::factory()->coach()->create();
        $other = User::factory()->student()->inProgress()->create();

        $this->actingAs($coach)->get(route('enrollments.show', $this->enrollment))->assertForbidden();
        $this->actingAs($other)->get(route('enrollments.show', $this->enrollment))->assertForbidden();
    }

    public function test_goals_are_removed_when_enrollment_is_cancelled(): void
    {
        $this->goal();
        $this->goal();

        $this->actingAs($this->student)
            ->delete(route('enrollments.destroy', $this->enrollment))
            ->assertRedirect();

        $this->assertSoftDeleted('enrollments', ['id' => $this->enrollment->id]);
        $this->assertDatabaseCount('enrollment_goals', 0);
    }

    public function test_store_returns_not_found_after_enrollment_cancelled(): void
    {
        $goal = $this->goal();
        $this->enrollment->delete();

        $this->actingAs($this->student)
            ->postJson(route('enrollments.goals.store', $this->enrollment->id), ['title' => 'x'])
            ->assertStatus(404);
        $this->assertDatabaseMissing('enrollment_goals', ['id' => $goal->id]);
    }
}
