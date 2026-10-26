<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\EnrollmentNote;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use App\UseCases\EnrollmentNote\DestroyAction;
use App\UseCases\EnrollmentNote\StoreAction;
use App\UseCases\EnrollmentNote\UpdateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * コーチメモ UseCase(追加 / 更新 / 削除)の単体検証。
 */
class ActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_records_author_and_enrollment(): void
    {
        $enrollment = Enrollment::factory()->create();
        $author = User::factory()->coach()->create();

        $note = (new StoreAction)($enrollment, $author, ['body' => '本文']);

        $this->assertSame($enrollment->id, $note->enrollment_id);
        $this->assertSame($author->id, $note->author_id);
        $this->assertSame('本文', $note->fresh()->body);
    }

    public function test_update_changes_only_body(): void
    {
        $author = User::factory()->coach()->create();
        $note = EnrollmentNote::factory()->byAuthor($author)->create(['body' => '元']);
        $enrollmentId = $note->enrollment_id;

        (new UpdateAction)($note, ['body' => '新']);

        $note->refresh();
        $this->assertSame('新', $note->body);
        $this->assertSame($author->id, $note->author_id);
        $this->assertSame($enrollmentId, $note->enrollment_id);
    }

    public function test_destroy_deletes_physically(): void
    {
        $note = EnrollmentNote::factory()->create();

        (new DestroyAction)($note);

        $this->assertDatabaseMissing('enrollment_notes', ['id' => $note->id]);
    }
}
