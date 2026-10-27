<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Learning;

use App\Enums\CertificationStatus;
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
 * B-B-03 回帰テスト: 受講生は「資格が公開中」の教材のみ閲覧でき、公開停止(archived)などの資格配下は 404 になる。
 */
class CertificationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: CertificationStatus, 1: EnrollmentStatus, 2: int}>
     */
    public static function visibilityCases(): array
    {
        return [
            '公開中 + 受講中 → 閲覧できる' => [CertificationStatus::Published, EnrollmentStatus::Learning, 200],
            '公開中 + 合格 → 閲覧できる' => [CertificationStatus::Published, EnrollmentStatus::Passed, 200],
            '公開停止 + 受講中 → 404' => [CertificationStatus::Archived, EnrollmentStatus::Learning, 404],
            '公開停止 + 合格 → 404' => [CertificationStatus::Archived, EnrollmentStatus::Passed, 404],
            '下書き + 受講中 → 404' => [CertificationStatus::Draft, EnrollmentStatus::Learning, 404],
        ];
    }

    /**
     * @dataProvider visibilityCases
     */
    public function test_part_chapter_section_follow_certification_status(
        CertificationStatus $certStatus,
        EnrollmentStatus $enrollmentStatus,
        int $expected,
    ): void {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->create(['status' => $certStatus->value]);
        Enrollment::factory()->for($student)->for($certification)->state(['status' => $enrollmentStatus->value])->create();
        $part = Part::factory()->for($certification)->create(['status' => ContentStatus::Published->value]);
        $chapter = Chapter::factory()->for($part)->create(['status' => ContentStatus::Published->value]);
        $section = Section::factory()->for($chapter)->create([
            'status' => ContentStatus::Published->value,
            'body' => '# テスト本文',
        ]);

        $this->actingAs($student);

        $this->get(route('learning.parts.show', $part))->assertStatus($expected);
        $this->get(route('learning.chapters.show', $chapter))->assertStatus($expected);
        $this->get(route('learning.sections.show', $section))->assertStatus($expected);
    }

    public function test_archiving_certification_hides_content_but_other_published_certification_stays_visible(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $archived = Certification::factory()->archived()->create();
        $published = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($archived)->learning()->create();
        Enrollment::factory()->for($student)->for($published)->learning()->create();
        $archivedPart = Part::factory()->for($archived)->create(['status' => ContentStatus::Published->value]);
        $publishedPart = Part::factory()->for($published)->create(['status' => ContentStatus::Published->value]);

        $this->actingAs($student);

        $this->get(route('learning.parts.show', $archivedPart))->assertNotFound();
        $this->get(route('learning.parts.show', $publishedPart))->assertOk();
    }
}
