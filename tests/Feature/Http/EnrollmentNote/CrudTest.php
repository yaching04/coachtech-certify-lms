<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentNote;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * コーチメモ(EnrollmentNote)の追加 / 編集 / 削除と、受講登録詳細での表示・認可(担当コーチ / 作成者 / 管理者 / 受講生)の検証。
 */
class CrudTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $coach;

    private User $otherCoach;

    private User $outsideCoach;

    private User $admin;

    private Certification $certification;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->student = User::factory()->student()->inProgress()->create();
        $this->coach = User::factory()->coach()->create(['name' => '担当コーチA']);
        $this->otherCoach = User::factory()->coach()->create(['name' => '担当コーチB']);
        $this->outsideCoach = User::factory()->coach()->create(['name' => '担当外コーチ']);
        $this->certification = Certification::factory()->published()->create();
        foreach ([$this->coach, $this->otherCoach] as $coach) {
            $this->certification->coaches()->attach($coach->id, [
                'id' => (string) Str::ulid(),
                'assigned_by_user_id' => $this->admin->id,
                'assigned_at' => now(),
            ]);
        }
        $this->enrollment = Enrollment::factory()->for($this->student)->for($this->certification)->learning()->create();
    }

    private function note(User $author, array $attributes = []): EnrollmentNote
    {
        return EnrollmentNote::factory()->forEnrollment($this->enrollment)->byAuthor($author)->create($attributes);
    }

    // ---- store ----

    public function test_assigned_coach_can_add_note(): void
    {
        $this->actingAs($this->coach)
            ->post(route('enrollments.notes.store', $this->enrollment), ['body' => "最近の様子\n2 行目"])
            ->assertRedirect(route('enrollments.show', $this->enrollment))
            ->assertSessionHas('success');

        $note = EnrollmentNote::firstOrFail();
        $this->assertSame($this->enrollment->id, $note->enrollment_id);
        $this->assertSame($this->coach->id, $note->author_id);
        $this->assertSame("最近の様子\n2 行目", $note->body);
    }

    public function test_admin_can_add_note_to_any_enrollment_and_is_recorded_as_author(): void
    {
        $this->actingAs($this->admin)
            ->post(route('enrollments.notes.store', $this->enrollment), ['body' => '運営観察'])
            ->assertRedirect(route('enrollments.show', $this->enrollment));

        $this->assertSame($this->admin->id, EnrollmentNote::firstOrFail()->author_id);
    }

    public function test_unassigned_coach_cannot_add_note(): void
    {
        $this->actingAs($this->outsideCoach)
            ->post(route('enrollments.notes.store', $this->enrollment), ['body' => 'x'])
            ->assertForbidden();

        $this->assertSame(0, EnrollmentNote::count());
    }

    public function test_student_cannot_add_note_even_to_own_enrollment(): void
    {
        $this->actingAs($this->student)
            ->post(route('enrollments.notes.store', $this->enrollment), ['body' => 'x'])
            ->assertForbidden();

        $this->assertSame(0, EnrollmentNote::count());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $note = $this->note($this->coach);

        $this->post(route('enrollments.notes.store', $this->enrollment), ['body' => 'x'])->assertRedirect(route('login'));
        $this->get(route('enrollment-notes.edit', $note))->assertRedirect(route('login'));
        $this->patch(route('enrollment-notes.update', $note), ['body' => 'x'])->assertRedirect(route('login'));
        $this->delete(route('enrollment-notes.destroy', $note))->assertRedirect(route('login'));
    }

    public function test_cannot_add_note_to_soft_deleted_enrollment(): void
    {
        $this->enrollment->delete();

        $this->actingAs($this->admin)
            ->post(route('enrollments.notes.store', $this->enrollment), ['body' => 'x'])
            ->assertNotFound();

        $this->assertSame(0, EnrollmentNote::count());
    }

    public function test_body_is_required_and_limited_to_2000_chars(): void
    {
        $this->actingAs($this->coach)
            ->from(route('enrollments.show', $this->enrollment))
            ->post(route('enrollments.notes.store', $this->enrollment), ['body' => ''])
            ->assertRedirect(route('enrollments.show', $this->enrollment))
            ->assertSessionHasErrors('body');

        $this->actingAs($this->coach)
            ->post(route('enrollments.notes.store', $this->enrollment), ['body' => str_repeat('あ', 2001)])
            ->assertSessionHasErrors('body');
        $this->assertSame(0, EnrollmentNote::count());

        $this->actingAs($this->coach)
            ->post(route('enrollments.notes.store', $this->enrollment), ['body' => str_repeat('あ', 2000)])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, EnrollmentNote::count());
    }

    // ---- edit / update ----

    public function test_author_coach_can_open_edit_page_and_update(): void
    {
        $note = $this->note($this->coach, ['body' => '元の本文']);

        $this->actingAs($this->coach)
            ->get(route('enrollment-notes.edit', $note))
            ->assertOk()
            ->assertSee('元の本文');

        $this->actingAs($this->coach)
            ->patch(route('enrollment-notes.update', $note), ['body' => '更新後'])
            ->assertRedirect(route('enrollments.show', $this->enrollment))
            ->assertSessionHas('success');

        $this->assertSame('更新後', $note->refresh()->body);
    }

    public function test_coach_cannot_edit_other_coachs_note(): void
    {
        $note = $this->note($this->otherCoach, ['body' => '他コーチ']);

        $this->actingAs($this->coach)->get(route('enrollment-notes.edit', $note))->assertForbidden();
        $this->actingAs($this->coach)->patch(route('enrollment-notes.update', $note), ['body' => '改ざん'])->assertForbidden();

        $this->assertSame('他コーチ', $note->refresh()->body);
    }

    public function test_admin_can_edit_any_note_without_changing_author(): void
    {
        $note = $this->note($this->coach, ['body' => 'コーチのメモ']);

        $this->actingAs($this->admin)->get(route('enrollment-notes.edit', $note))->assertOk();
        $this->actingAs($this->admin)
            ->patch(route('enrollment-notes.update', $note), ['body' => '管理者が是正'])
            ->assertRedirect(route('enrollments.show', $this->enrollment));

        $note->refresh();
        $this->assertSame('管理者が是正', $note->body);
        $this->assertSame($this->coach->id, $note->author_id);
    }

    public function test_coach_who_is_no_longer_assigned_cannot_edit_own_note(): void
    {
        $note = $this->note($this->coach);
        $this->certification->coaches()->detach($this->coach->id);

        $this->actingAs($this->coach)->get(route('enrollment-notes.edit', $note))->assertForbidden();
        $this->actingAs($this->coach)->patch(route('enrollment-notes.update', $note), ['body' => 'x'])->assertForbidden();
        $this->actingAs($this->coach)->delete(route('enrollment-notes.destroy', $note))->assertForbidden();
    }

    public function test_unassigned_coach_and_student_cannot_edit_or_update(): void
    {
        $note = $this->note($this->coach);

        foreach ([$this->outsideCoach, $this->student] as $user) {
            $this->actingAs($user)->get(route('enrollment-notes.edit', $note))->assertForbidden();
            $this->actingAs($user)->patch(route('enrollment-notes.update', $note), ['body' => 'x'])->assertForbidden();
        }
    }

    public function test_update_validates_body(): void
    {
        $note = $this->note($this->coach, ['body' => '元']);

        $this->actingAs($this->coach)
            ->from(route('enrollment-notes.edit', $note))
            ->patch(route('enrollment-notes.update', $note), ['body' => ''])
            ->assertRedirect(route('enrollment-notes.edit', $note))
            ->assertSessionHasErrors('body');
        $this->actingAs($this->coach)
            ->patch(route('enrollment-notes.update', $note), ['body' => str_repeat('あ', 2001)])
            ->assertSessionHasErrors('body');

        $this->assertSame('元', $note->refresh()->body);
    }

    public function test_cannot_edit_note_of_soft_deleted_enrollment(): void
    {
        $note = $this->note($this->coach);
        $this->enrollment->delete();

        $this->actingAs($this->admin)->get(route('enrollment-notes.edit', $note))->assertForbidden();
        $this->actingAs($this->admin)->patch(route('enrollment-notes.update', $note), ['body' => 'x'])->assertForbidden();
        $this->actingAs($this->admin)->delete(route('enrollment-notes.destroy', $note))->assertForbidden();
    }

    // ---- destroy ----

    public function test_author_coach_can_delete_own_note(): void
    {
        $note = $this->note($this->coach);

        $this->actingAs($this->coach)
            ->delete(route('enrollment-notes.destroy', $note))
            ->assertRedirect(route('enrollments.show', $this->enrollment))
            ->assertSessionHas('success');

        $this->assertModelMissing($note);
    }

    public function test_coach_cannot_delete_other_coachs_note(): void
    {
        $note = $this->note($this->otherCoach);

        $this->actingAs($this->coach)->delete(route('enrollment-notes.destroy', $note))->assertForbidden();

        $this->assertModelExists($note);
    }

    public function test_admin_can_delete_any_note(): void
    {
        $note = $this->note($this->coach);

        $this->actingAs($this->admin)->delete(route('enrollment-notes.destroy', $note))
            ->assertRedirect(route('enrollments.show', $this->enrollment));

        $this->assertModelMissing($note);
    }

    public function test_unassigned_coach_and_student_cannot_delete(): void
    {
        $note = $this->note($this->coach);

        foreach ([$this->outsideCoach, $this->student] as $user) {
            $this->actingAs($user)->delete(route('enrollment-notes.destroy', $note))->assertForbidden();
        }

        $this->assertModelExists($note);
    }

    // ---- 受講登録詳細での表示 ----

    public function test_coach_sees_all_notes_with_actions_only_for_own(): void
    {
        $this->note($this->coach, ['body' => '自分のメモ本文']);
        $other = $this->note($this->otherCoach, ['body' => '他コーチのメモ本文']);
        $mine = EnrollmentNote::where('author_id', $this->coach->id)->firstOrFail();

        $response = $this->actingAs($this->coach)->get(route('enrollments.show', $this->enrollment));

        $response->assertOk()
            ->assertSee('コーチメモ')
            ->assertSee('自分のメモ本文')
            ->assertSee('他コーチのメモ本文')
            ->assertSee('担当コーチB')
            ->assertSee(route('enrollment-notes.edit', $mine), false)
            ->assertDontSee(route('enrollment-notes.edit', $other), false)
            ->assertSee(route('enrollments.notes.store', $this->enrollment), false);
    }

    public function test_admin_sees_all_notes_with_actions_for_every_note(): void
    {
        $a = $this->note($this->coach, ['body' => 'メモ A']);
        $b = $this->note($this->otherCoach, ['body' => 'メモ B']);

        $this->actingAs($this->admin)
            ->get(route('enrollments.show', $this->enrollment))
            ->assertOk()
            ->assertSee('メモ A')
            ->assertSee('メモ B')
            ->assertSee(route('enrollment-notes.edit', $a), false)
            ->assertSee(route('enrollment-notes.edit', $b), false)
            ->assertSee(route('enrollments.notes.store', $this->enrollment), false);
    }

    public function test_notes_are_listed_newest_first(): void
    {
        $this->note($this->coach, ['body' => '古いメモ', 'created_at' => now()->subDays(3)]);
        $this->note($this->coach, ['body' => '新しいメモ', 'created_at' => now()->subDay()]);

        $this->actingAs($this->coach)
            ->get(route('enrollments.show', $this->enrollment))
            ->assertSeeInOrder(['新しいメモ', '古いメモ']);
    }

    public function test_student_never_sees_note_section_or_content(): void
    {
        $this->note($this->coach, ['body' => '受講生に見せてはいけないメモ']);

        $this->actingAs($this->student)
            ->get(route('enrollments.show', $this->enrollment))
            ->assertOk()
            ->assertDontSee('コーチメモ')
            ->assertDontSee('受講生に見せてはいけないメモ')
            ->assertDontSee(route('enrollments.notes.store', $this->enrollment), false);
    }

    public function test_unassigned_coach_cannot_open_enrollment_detail(): void
    {
        $this->note($this->coach, ['body' => '秘密のメモ']);

        $this->actingAs($this->outsideCoach)
            ->get(route('enrollments.show', $this->enrollment))
            ->assertForbidden();
    }

    public function test_notes_of_soft_deleted_enrollment_are_excluded_from_list(): void
    {
        $this->note($this->coach, ['body' => '削除前のメモ']);
        $this->enrollment->delete();

        $this->actingAs($this->admin)
            ->get(route('enrollments.show', $this->enrollment))
            ->assertOk()
            ->assertDontSee('削除前のメモ')
            ->assertDontSee('コーチメモ');
    }

    public function test_empty_message_is_shown_when_no_notes(): void
    {
        $this->actingAs($this->coach)
            ->get(route('enrollments.show', $this->enrollment))
            ->assertSee('まだメモがありません。');
    }

    public function test_note_survives_after_author_is_soft_deleted_and_shows_name(): void
    {
        $this->note($this->otherCoach, ['body' => '離任コーチのメモ']);
        $this->otherCoach->delete();

        $this->actingAs($this->admin)
            ->get(route('enrollments.show', $this->enrollment))
            ->assertSee('離任コーチのメモ')
            ->assertSee('担当コーチB');
    }

    public function test_notes_are_scoped_to_their_enrollment(): void
    {
        $otherEnrollment = Enrollment::factory()
            ->for(User::factory()->student()->inProgress()->create())
            ->for($this->certification)
            ->learning()
            ->create();
        EnrollmentNote::factory()->forEnrollment($otherEnrollment)->byAuthor($this->coach)->create(['body' => '別の受講生のメモ']);

        $this->actingAs($this->coach)
            ->get(route('enrollments.show', $this->enrollment))
            ->assertDontSee('別の受講生のメモ');
    }
}
