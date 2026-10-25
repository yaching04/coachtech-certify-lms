<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * プラン更新 UpdateRequest のバリデーション検証。
 * StoreRequest と同一ルールのため代表ケースのみ検証し、authorize は admin のみ true を検証する。
 */
class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(array $overrides, string $expectedErrorField): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create();
        $payload = array_merge([
            'name' => '更新プラン',
            'duration_days' => 90,
            'default_meeting_quota' => 3,
        ], $overrides);

        // Act
        $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    public function test_authorize_returns_false_for_non_admin(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $plan = Plan::factory()->create();

        // Act
        $response = $this->actingAs($coach)->putJson(route('admin.plans.update', $plan), [
            'name' => '更新プラン',
            'duration_days' => 90,
            'default_meeting_quota' => 3,
        ]);

        // Assert
        $response->assertForbidden();
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'name 未指定で 422' => [['name' => ''], 'name'],
            'duration_days 0 で 422' => [['duration_days' => 0], 'duration_days'],
            'default_meeting_quota 負数で 422' => [['default_meeting_quota' => -1], 'default_meeting_quota'],
            'description 2001 文字で 422' => [['description' => str_repeat('b', 2001)], 'description'],
        ];
    }
}
