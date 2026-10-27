<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Learning;

use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B-B-09: 受講登録していない資格の教材詳細(Part / Chapter / Section)を直リンクで閲覧できないことを検証する。
 */
class EnrollmentGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_enrolled_student_gets_403_on_part_chapter_and_section(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrolled = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($enrolled)->learning()->create();

        [$part, $chapter, $section] = $this->buildContent(Certification::factory()->published()->create());

        $this->actingAs($student)->get(route('learning.parts.show', $part))->assertForbidden();
        $this->actingAs($student)->get(route('learning.chapters.show', $chapter))->assertForbidden();
        $this->actingAs($student)->get(route('learning.sections.show', $section))->assertForbidden();
    }

    public function test_enrolled_certification_content_is_still_visible(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrolled = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($enrolled)->learning()->create();

        [$part, $chapter, $section] = $this->buildContent($enrolled);

        $this->actingAs($student)->get(route('learning.parts.show', $part))->assertOk();
        $this->actingAs($student)->get(route('learning.chapters.show', $chapter))->assertOk();
        $this->actingAs($student)->get(route('learning.sections.show', $section))->assertOk();
    }

    public function test_passed_enrollment_can_view_content(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($certification)->passed()->create();

        [$part, $chapter, $section] = $this->buildContent($certification);

        $this->actingAs($student)->get(route('learning.parts.show', $part))->assertOk();
        $this->actingAs($student)->get(route('learning.chapters.show', $chapter))->assertOk();
        $this->actingAs($student)->get(route('learning.sections.show', $section))->assertOk();
    }

    public function test_failed_enrollment_gets_403_on_part_chapter_and_section(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($certification)->state(['status' => EnrollmentStatus::Failed->value])->create();

        [$part, $chapter, $section] = $this->buildContent($certification);

        $this->actingAs($student)->get(route('learning.parts.show', $part))->assertForbidden();
        $this->actingAs($student)->get(route('learning.chapters.show', $chapter))->assertForbidden();
        $this->actingAs($student)->get(route('learning.sections.show', $section))->assertForbidden();
    }

    public function test_other_students_enrollment_does_not_grant_access(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        Enrollment::factory()->for($other)->for($certification)->learning()->create();

        [$part, $chapter, $section] = $this->buildContent($certification);

        $this->actingAs($student)->get(route('learning.parts.show', $part))->assertForbidden();
        $this->actingAs($student)->get(route('learning.chapters.show', $chapter))->assertForbidden();
        $this->actingAs($student)->get(route('learning.sections.show', $section))->assertForbidden();
    }

    public function test_forbidden_section_access_does_not_start_learning_session(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        [, , $section] = $this->buildContent(Certification::factory()->published()->create());

        $this->actingAs($student)->get(route('learning.sections.show', $section))->assertForbidden();

        $this->assertDatabaseMissing('learning_sessions', ['user_id' => $student->id]);
    }

    public function test_failed_enrollment_section_access_does_not_start_learning_session(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($certification)->state(['status' => EnrollmentStatus::Failed->value])->create();
        [, , $section] = $this->buildContent($certification);

        $this->actingAs($student)->get(route('learning.sections.show', $section))->assertForbidden();

        $this->assertDatabaseMissing('learning_sessions', ['user_id' => $student->id]);
    }

    /**
     * @return array{0: Part, 1: Chapter, 2: Section}
     */
    private function buildContent(Certification $certification): array
    {
        $part = Part::factory()->for($certification)->create(['status' => ContentStatus::Published->value]);
        $chapter = Chapter::factory()->for($part)->create(['status' => ContentStatus::Published->value]);
        $section = Section::factory()->for($chapter)->create([
            'status' => ContentStatus::Published->value,
            'body' => '# テスト本文',
        ]);

        return [$part, $chapter, $section];
    }
}
