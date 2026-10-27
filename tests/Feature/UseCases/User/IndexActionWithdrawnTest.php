<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\User;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\UserWithdrawalService;
use App\UseCases\User\IndexAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B-B-04 回帰テスト: 退会済(soft delete 済)ユーザーは「退会済」フィルタのときだけ一覧に含まれる。
 */
class IndexActionWithdrawnTest extends TestCase
{
    use RefreshDatabase;

    private User $active;

    private User $withdrawn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->active = User::factory()->student()->create(['name' => '在籍太郎', 'status' => UserStatus::InProgress->value]);
        $this->withdrawn = User::factory()->student()->create(['name' => '退会花子', 'status' => UserStatus::InProgress->value]);
        app(UserWithdrawalService::class)->withdraw($this->withdrawn);
    }

    /**
     * @return list<string>
     */
    private function ids(?string $keyword = null, ?UserStatus $status = null): array
    {
        return (new IndexAction)($keyword, null, $status)->pluck('id')->all();
    }

    public function test_withdrawn_user_is_excluded_without_status_filter(): void
    {
        $ids = $this->ids();

        $this->assertContains($this->active->id, $ids);
        $this->assertNotContains($this->withdrawn->id, $ids);
    }

    public function test_withdrawn_user_is_excluded_with_in_progress_filter(): void
    {
        $ids = $this->ids(null, UserStatus::InProgress);

        $this->assertContains($this->active->id, $ids);
        $this->assertNotContains($this->withdrawn->id, $ids);
    }

    public function test_withdrawn_user_is_excluded_from_keyword_search(): void
    {
        $this->assertSame([], $this->ids('退会花子'));
    }

    public function test_withdrawn_user_is_included_only_with_withdrawn_filter(): void
    {
        $ids = $this->ids(null, UserStatus::Withdrawn);

        $this->assertSame([$this->withdrawn->id], $ids);
    }
}
