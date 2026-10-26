<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 個人目標追加 StoreRequest のバリデーション検証。title 必須(100 字)/ description 任意(1000 字)/ target_date 任意(Y-m-d)。
 */
class StoreRequestTest extends TestCase
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
            'target date' => [['title' => '目標', 'target_date' => '2030-12-31']],
            'null optional' => [['title' => '目標', 'description' => null, 'target_date' => null]],
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
            'target date not a date' => [['title' => '目標', 'target_date' => 'abc'], 'target_date'],
        ];
    }

    #[DataProvider('validPayloads')]
    public function test_validation_passes(array $payload): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        $this->actingAs($student)
            ->postJson(route('enrollments.goals.store', $enrollment), $payload)
            ->assertRedirect(route('enrollments.show', $enrollment));
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(array $payload, string $field): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        $this->actingAs($student)
            ->postJson(route('enrollments.goals.store', $enrollment), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }
}
