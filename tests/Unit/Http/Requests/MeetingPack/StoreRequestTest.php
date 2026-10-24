<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\MeetingPack;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 面談パック新規作成 StoreRequest のバリデーション検証。
 * 必須 name / meeting_count / price と任意 description / stripe_price_id / sort_order の
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
            'name' => '5 回パック',
            'meeting_count' => 5,
            'price' => 12000,
        ];
    }

    #[DataProvider('validPayloads')]
    public function test_validation_passes(array $overrides): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(
            route('admin.meeting-packs.store'),
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
            route('admin.meeting-packs.store'),
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
            'meeting_count 下限 1' => [['meeting_count' => 1]],
            'meeting_count 上限 100' => [['meeting_count' => 100]],
            'price 下限 0' => [['price' => 0]],
            'price 上限 1,000,000' => [['price' => 1000000]],
            'stripe_price_id 255 文字' => [['stripe_price_id' => str_repeat('p', 255)]],
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
            'meeting_count 未指定で 422' => [['meeting_count' => null], 'meeting_count'],
            'meeting_count 0 で 422' => [['meeting_count' => 0], 'meeting_count'],
            'meeting_count 101 で 422' => [['meeting_count' => 101], 'meeting_count'],
            'meeting_count 小数で 422' => [['meeting_count' => 1.5], 'meeting_count'],
            'price 未指定で 422' => [['price' => null], 'price'],
            'price 負数で 422' => [['price' => -1], 'price'],
            'price 1,000,001 で 422' => [['price' => 1000001], 'price'],
            'price 文字列で 422' => [['price' => 'abc'], 'price'],
            'stripe_price_id 256 文字で 422' => [['stripe_price_id' => str_repeat('p', 256)], 'stripe_price_id'],
            'sort_order 負数で 422' => [['sort_order' => -1], 'sort_order'],
            'sort_order 65536 で 422' => [['sort_order' => 65536], 'sort_order'],
        ];
    }
}
