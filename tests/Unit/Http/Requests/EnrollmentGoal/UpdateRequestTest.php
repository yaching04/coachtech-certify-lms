<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 個人目標編集 UpdateRequest のバリデーション検証(ルールは StoreRequest と同一)。
 */
class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function validPayloads(): array
    {
        return [
            'title only' => [['title' => '目標']],
            'title 100 chars' => [['title' => str_repeat('あ', 100)]],
            'description 1000 chars' => [['title' => '目標', 'description' => str_repeat('あ', 1000)]],
            'clear optional' => [['title' => '目標', 'description' => null, 'target_date' => null]],
            'target date' => [['title' => '目標', 'target_date' => '2030-12-31']],
        ];
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'title missing' => [[], 'title'],
            'title empty' => [['title' => ''], 'title'],
            'title 101 chars' => [['title' => str_repeat('あ', 101)], 'title'],
            'description 1001 chars' => [['title' => '目標', 'description' => str_repeat('あ', 1001)], 'description'],
            'target date wrong format' => [['title' => '目標', 'target_date' => '2030/12/31'], 'target_date'],
        ];
    }

    private function goalOf(User $student): EnrollmentGoal
    {
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        return EnrollmentGoal::factory()->forEnrollment($enrollment)->create();
    }

    #[DataProvider('validPayloads')]
    public function test_validation_passes(array $payload): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $goal = $this->goalOf($student);

        $this->actingAs($student)
            ->patchJson(route('enrollment-goals.update', $goal), $payload)
            ->assertRedirect(route('enrollments.show', $goal->enrollment_id));
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(array $payload, string $field): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $goal = $this->goalOf($student);

        $this->actingAs($student)
            ->patchJson(route('enrollment-goals.update', $goal), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }
}
