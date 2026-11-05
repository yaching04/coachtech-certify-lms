<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 面談パック更新 UpdateRequest のバリデーション検証。
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
        $plan = MeetingPack::factory()->create();
        $payload = array_merge([
            'name' => '更新パック',
            'meeting_count' => 3,
            'price' => 9000,
        ], $overrides);

        // Act
        $response = $this->actingAs($admin)->patchJson(route('admin.meeting-packs.update', $plan), $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    public function test_authorize_returns_false_for_non_admin(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $plan = MeetingPack::factory()->create();

        // Act
        $response = $this->actingAs($coach)->patchJson(route('admin.meeting-packs.update', $plan), [
            'name' => '更新パック',
            'meeting_count' => 3,
            'price' => 9000,
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
            'meeting_count 0 で 422' => [['meeting_count' => 0], 'meeting_count'],
            'price 負数で 422' => [['price' => -1], 'price'],
            'description 2001 文字で 422' => [['description' => str_repeat('b', 2001)], 'description'],
        ];
    }
}
