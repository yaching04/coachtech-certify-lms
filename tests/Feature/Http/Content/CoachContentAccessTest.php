<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Content;

use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionImage;
use App\Models\SectionQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

/**
 * B-B-01 回帰テスト: コーチは担当資格配下の教材管理(Part / Chapter / Section / 演習問題 / 教材内画像 / 出題分野)を操作でき、
 * 担当外の資格配下は引き続き 403 になる。
 */
class CoachContentAccessTest extends TestCase
{
    use ContentTestHelpers, RefreshDatabase;

    private User $coach;

    private Certification $assigned;

    private Certification $other;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->coach = User::factory()->coach()->create();
        $this->assigned = Certification::factory()->published()->create();
        $this->other = Certification::factory()->published()->create();
        $this->assignCoach($this->coach, $this->assigned);
    }

    public function test_assigned_coach_can_open_every_management_screen(): void
    {
        [$part, $chapter, $section] = $this->makePartChain($this->assigned, 'draft');
        $question = SectionQuestion::factory()->forSection($section)->withOptions()->create();
        $this->makeCategory($this->assigned);

        $this->actingAs($this->coach);

        $this->get(route('admin.certifications.parts.index', $this->assigned))->assertOk();
        $this->get(route('admin.parts.show', $part))->assertOk();
        $this->get(route('admin.chapters.show', $chapter))->assertOk();
        $this->get(route('admin.sections.show', $section))->assertOk();
        $this->get(route('admin.sections.questions.index', $section))->assertOk();
        $this->get(route('admin.sections.questions.create', $section))->assertOk();
        $this->get(route('admin.section-questions.show', $question))->assertOk();
        $this->get(route('admin.certifications.question-categories.index', $this->assigned))->assertOk();
    }

    public function test_unassigned_coach_gets_403_on_every_management_screen(): void
    {
        [$part, $chapter, $section] = $this->makePartChain($this->other, 'draft');
        $question = SectionQuestion::factory()->forSection($section)->withOptions()->create();

        $this->actingAs($this->coach);

        $this->get(route('admin.certifications.parts.index', $this->other))->assertForbidden();
        $this->get(route('admin.parts.show', $part))->assertForbidden();
        $this->get(route('admin.chapters.show', $chapter))->assertForbidden();
        $this->get(route('admin.sections.show', $section))->assertForbidden();
        $this->get(route('admin.sections.questions.index', $section))->assertForbidden();
        $this->get(route('admin.sections.questions.create', $section))->assertForbidden();
        $this->get(route('admin.section-questions.show', $question))->assertForbidden();
        $this->get(route('admin.certifications.question-categories.index', $this->other))->assertForbidden();
    }

    public function test_assigned_coach_can_create_publish_unpublish_and_delete_content(): void
    {
        [$part, $chapter, $section] = $this->makePartChain($this->assigned, 'draft');

        $this->actingAs($this->coach);

        $this->post(route('admin.certifications.parts.store', $this->assigned), ['title' => '新しいPart'])
            ->assertRedirect();
        $this->assertDatabaseHas('parts', ['certification_id' => $this->assigned->id, 'title' => '新しいPart']);

        $this->post(route('admin.sections.publish', $section))->assertRedirect();
        $this->post(route('admin.sections.unpublish', $section))->assertRedirect();
        $this->post(route('admin.chapters.publish', $chapter))->assertRedirect();
        $this->post(route('admin.parts.publish', $part))->assertRedirect();
        $this->post(route('admin.parts.unpublish', $part))->assertRedirect();
    }

    public function test_assigned_coach_can_reorder_content(): void
    {
        $a = Part::factory()->forCertification($this->assigned)->state(['order' => 1])->create();
        $b = Part::factory()->forCertification($this->assigned)->state(['order' => 2])->create();
        $c1 = Chapter::factory()->forPart($a)->state(['order' => 1])->create();
        $c2 = Chapter::factory()->forPart($a)->state(['order' => 2])->create();
        $s1 = Section::factory()->forChapter($c1)->state(['order' => 1])->create();
        $s2 = Section::factory()->forChapter($c1)->state(['order' => 2])->create();

        $this->actingAs($this->coach);

        $this->patch(route('admin.certifications.parts.reorder', $this->assigned), ['ids' => [$b->id, $a->id]])
            ->assertRedirect();
        $this->patch(route('admin.parts.chapters.reorder', $a), ['ids' => [$c2->id, $c1->id]])
            ->assertRedirect();
        $this->patch(route('admin.chapters.sections.reorder', $c1), ['ids' => [$s2->id, $s1->id]])
            ->assertRedirect();

        $this->assertSame(1, $b->fresh()->order);
        $this->assertSame(1, $c2->fresh()->order);
        $this->assertSame(1, $s2->fresh()->order);
    }

    public function test_unassigned_coach_cannot_mutate_content(): void
    {
        [$part, $chapter, $section] = $this->makePartChain($this->other, 'draft');

        $this->actingAs($this->coach);

        $this->post(route('admin.certifications.parts.store', $this->other), ['title' => 'x'])->assertForbidden();
        $this->post(route('admin.parts.publish', $part))->assertForbidden();
        $this->patch(route('admin.certifications.parts.reorder', $this->other), ['ids' => [$part->id]])->assertForbidden();
        $this->delete(route('admin.sections.destroy', $section))->assertForbidden();
        $this->assertDatabaseHas('sections', ['id' => $section->id]);
    }

    public function test_assigned_coach_can_upload_and_delete_section_image(): void
    {
        [, , $section] = $this->makePartChain($this->assigned, 'draft');

        $this->actingAs($this->coach)
            ->postJson(route('admin.sections.images.store', $section), [
                'file' => UploadedFile::fake()->image('cover.png', 800, 600),
            ])
            ->assertCreated();

        $image = SectionImage::query()->where('section_id', $section->id)->firstOrFail();

        $this->delete(route('admin.section-images.destroy', $image))->assertNoContent();
        $this->assertDatabaseMissing('section_images', ['id' => $image->id]);
    }

    public function test_unassigned_coach_cannot_upload_section_image(): void
    {
        [, , $section] = $this->makePartChain($this->other, 'draft');

        $this->actingAs($this->coach)
            ->postJson(route('admin.sections.images.store', $section), [
                'file' => UploadedFile::fake()->image('cover.png', 800, 600),
            ])
            ->assertForbidden();
    }

    public function test_assigned_coach_can_manage_question_categories(): void
    {
        $this->actingAs($this->coach);

        $this->post(route('admin.certifications.question-categories.store', $this->assigned), ['name' => '分野A', 'slug' => 'bunya-a'])
            ->assertRedirect();
        $this->assertDatabaseHas('question_categories', ['certification_id' => $this->assigned->id, 'name' => '分野A']);

        $otherCategory = $this->makeCategory($this->other);
        $this->patch(route('admin.question-categories.update', $otherCategory), ['name' => '変更', 'slug' => 'henko'])->assertForbidden();
        $this->delete(route('admin.question-categories.destroy', $otherCategory))->assertForbidden();
    }
}
