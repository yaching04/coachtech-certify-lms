<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Enrollment;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B-B-08: 受講生が目標受験日を更新したあとは、ダッシュボードではなく当該受講登録の詳細画面に戻ることを担保する。
 */
class UpdateExamDateRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_is_redirected_to_enrollment_show_not_dashboard(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->for(Certification::factory()->published()->create())->learning()->create(['exam_date' => null]);

        $response = $this->actingAs($student)
            ->patch(route('enrollments.updateExamDate', $enrollment), ['exam_date' => now()->addDays(30)->toDateString()]);

        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertNotSame(route('dashboard.index'), $response->headers->get('Location'));
    }

    public function test_updated_date_and_success_message_are_visible_on_show_page(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->for(Certification::factory()->published()->create())->learning()->create(['exam_date' => null]);
        $examDate = now()->addDays(45)->toDateString();

        $this->actingAs($student)
            ->followingRedirects()
            ->patch(route('enrollments.updateExamDate', $enrollment), ['exam_date' => $examDate])
            ->assertOk()
            ->assertSee($examDate)
            ->assertSee('目標受験日を更新しました。');
    }

    public function test_validation_error_does_not_redirect_to_dashboard(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $show = route('enrollments.show', $enrollment);

        $this->actingAs($student)
            ->from($show)
            ->patch(route('enrollments.updateExamDate', $enrollment), ['exam_date' => now()->subDay()->toDateString()])
            ->assertRedirect($show)
            ->assertSessionHasErrors('exam_date');
    }
}
