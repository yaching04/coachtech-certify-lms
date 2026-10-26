<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EnrollmentNote モデルの検証(リレーション・論理削除済みの親 / 作成者の取得・連動削除)。
 */
class EnrollmentNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_relations(): void
    {
        $enrollment = Enrollment::factory()->create();
        $author = User::factory()->coach()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->byAuthor($author)->create();

        $this->assertTrue($note->enrollment->is($enrollment));
        $this->assertTrue($note->author->is($author));
        $this->assertTrue($enrollment->notes->first()->is($note));
    }

    public function test_soft_deleted_enrollment_and_author_are_still_resolvable(): void
    {
        $enrollment = Enrollment::factory()->create();
        $author = User::factory()->coach()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->byAuthor($author)->create();

        $enrollment->delete();
        $author->delete();
        $note = $note->fresh();

        $this->assertTrue($note->enrollment->trashed());
        $this->assertTrue($note->author->trashed());
    }

    public function test_notes_are_kept_when_enrollment_is_soft_deleted(): void
    {
        $note = EnrollmentNote::factory()->create();

        $note->enrollment->delete();

        $this->assertDatabaseHas('enrollment_notes', ['id' => $note->id]);
    }

    public function test_notes_are_removed_when_enrollment_is_force_deleted(): void
    {
        $note = EnrollmentNote::factory()->create();

        $note->enrollment->forceDelete();

        $this->assertDatabaseMissing('enrollment_notes', ['id' => $note->id]);
    }
}
