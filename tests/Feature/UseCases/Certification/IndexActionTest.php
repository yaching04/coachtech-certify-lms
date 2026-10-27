<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Certification;

use App\Enums\CertificationStatus;
use App\Models\Certification;
use App\Models\User;
use App\UseCases\Certification\IndexAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B-B-11: 資格マスタ一覧の状態フィルタが、指定した status で絞り込まれることを検証する。
 */
class IndexActionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: CertificationStatus}>
     */
    public static function statuses(): array
    {
        return [
            '公開中' => [CertificationStatus::Published],
            '下書き' => [CertificationStatus::Draft],
            'アーカイブ' => [CertificationStatus::Archived],
        ];
    }

    /**
     * @dataProvider statuses
     */
    public function test_status_filter_returns_only_the_specified_status(CertificationStatus $status): void
    {
        $admin = User::factory()->admin()->create();
        Certification::factory()->published()->count(2)->create();
        Certification::factory()->draft()->count(2)->create();
        Certification::factory()->archived()->count(2)->create();

        $result = app(IndexAction::class)($admin, null, $status, null, null);

        $this->assertSame(2, $result->total());
        $this->assertTrue(
            $result->getCollection()->every(fn (Certification $c) => $c->status === $status),
        );
    }

    public function test_without_status_filter_returns_all_statuses(): void
    {
        $admin = User::factory()->admin()->create();
        Certification::factory()->published()->create();
        Certification::factory()->draft()->create();
        Certification::factory()->archived()->create();

        $result = app(IndexAction::class)($admin, null, null, null, null);

        $this->assertSame(3, $result->total());
    }

    public function test_status_filter_combines_with_keyword(): void
    {
        $admin = User::factory()->admin()->create();
        Certification::factory()->published()->create(['name' => 'Alpha Published']);
        Certification::factory()->published()->create(['name' => 'Beta Published']);
        Certification::factory()->archived()->create(['name' => 'Alpha Archived']);

        $result = app(IndexAction::class)($admin, 'Alpha', CertificationStatus::Published, null, null);

        $this->assertSame(['Alpha Published'], $result->getCollection()->pluck('name')->all());
    }
}
