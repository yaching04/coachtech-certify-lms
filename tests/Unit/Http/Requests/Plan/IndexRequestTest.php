<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Plan;

use App\Enums\PlanStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * プラン一覧 IndexRequest のバリデーション検証。
 * キーワード / status / page の nullable フィルタを網羅する。
 */
class IndexRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_all_filters_set(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->get(route('admin.plans.index', [
            'keyword' => 'プラン',
            'status' => PlanStatus::Published->value,
            'page' => 1,
        ]));

        // Assert
        $response->assertSuccessful();
    }

    #[DataProvider('invalidFilterPayloads')]
    public function test_validation_fails(array $params, string $expectedErrorField): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->getJson(route('admin.plans.index', $params));

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidFilterPayloads(): array
    {
        return [
            'keyword 101 文字で 422' => [['keyword' => str_repeat('a', 101)], 'keyword'],
            'status 不正値で 422' => [['status' => 'unknown'], 'status'],
            'page 0 で 422' => [['page' => 0], 'page'],
        ];
    }
}
