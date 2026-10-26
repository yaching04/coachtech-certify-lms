<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Plan;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * プラン新規作成 StoreRequest のバリデーション検証。
 * 必須 name / duration_days / default_meeting_quota と任意 description / sort_order の
 * 文字数・範囲の境界値を valid + invalid で網羅する。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private static function basePayload(): array
    {
        return [
            'name' => '3 ヶ月プラン 12 回',
            'duration_days' => 90,
            'default_meeting_quota' => 12,
        ];
    }

    #[DataProvider('validPayloads')]
    public function test_validation_passes(array $overrides): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(
            route('admin.plans.store'),
            array_merge(self::basePayload(), $overrides),
        );

        // Assert
        $response->assertSessionDoesntHaveErrors();
        $response->assertStatus(302);
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(array $overrides, string $expectedErrorField): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(
            route('admin.plans.store'),
            array_merge(self::basePayload(), $overrides),
        );

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function validPayloads(): array
    {
        return [
            '必須項目のみ' => [[]],
            'name 100 文字' => [['name' => str_repeat('a', 100)]],
            'description 2000 文字' => [['description' => str_repeat('b', 2000)]],
            'duration_days 下限 1' => [['duration_days' => 1]],
            'duration_days 上限 3650' => [['duration_days' => 3650]],
            'default_meeting_quota 下限 0' => [['default_meeting_quota' => 0]],
            'default_meeting_quota 上限 1000' => [['default_meeting_quota' => 1000]],
            'sort_order 下限 0' => [['sort_order' => 0]],
            'sort_order 上限 65535' => [['sort_order' => 65535]],
        ];
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'name 未指定で 422' => [['name' => ''], 'name'],
            'name 101 文字で 422' => [['name' => str_repeat('a', 101)], 'name'],
            'description 2001 文字で 422' => [['description' => str_repeat('b', 2001)], 'description'],
            'duration_days 未指定で 422' => [['duration_days' => null], 'duration_days'],
            'duration_days 0 で 422' => [['duration_days' => 0], 'duration_days'],
            'duration_days 3651 で 422' => [['duration_days' => 3651], 'duration_days'],
            'duration_days 小数で 422' => [['duration_days' => 1.5], 'duration_days'],
            'duration_days 文字列で 422' => [['duration_days' => 'abc'], 'duration_days'],
            'default_meeting_quota 未指定で 422' => [['default_meeting_quota' => null], 'default_meeting_quota'],
            'default_meeting_quota 負数で 422' => [['default_meeting_quota' => -1], 'default_meeting_quota'],
            'default_meeting_quota 1001 で 422' => [['default_meeting_quota' => 1001], 'default_meeting_quota'],
            'default_meeting_quota 小数で 422' => [['default_meeting_quota' => 1.5], 'default_meeting_quota'],
            'sort_order 負数で 422' => [['sort_order' => -1], 'sort_order'],
            'sort_order 65536 で 422' => [['sort_order' => 65536], 'sort_order'],
        ];
    }
}
