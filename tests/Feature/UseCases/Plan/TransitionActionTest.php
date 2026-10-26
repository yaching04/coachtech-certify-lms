<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanInvalidTransitionException;
use App\Models\Plan;
use App\Models\User;
use App\UseCases\Plan\ArchiveAction;
use App\UseCases\Plan\PublishAction;
use App\UseCases\Plan\UnarchiveAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * プランの状態遷移 Action(Publish / Archive / Unarchive)を 3 状態 × 3 Action のマトリクスで検証する。
 * 許可される遷移は draft → published / published → archived / archived → draft の 3 本のみ。
 */
class TransitionActionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param class-string $actionClass
     */
    #[DataProvider('transitionMatrix')]
    public function test_transition(string $actionClass, string $from, ?string $expectedTo): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->{$from}()->create();

        if ($expectedTo === null) {
            $this->expectException(PlanInvalidTransitionException::class);
        }

        $result = (new $actionClass)($plan, $admin);

        $this->assertSame(PlanStatus::from($expectedTo), $result->status);
        $this->assertSame($admin->id, $result->updated_by_user_id);
    }

    /**
     * @return array<string, array{0: class-string, 1: string, 2: ?string}>
     */
    public static function transitionMatrix(): array
    {
        return [
            'publish: draft → published' => [PublishAction::class, 'draft', 'published'],
            'publish: published は不可' => [PublishAction::class, 'published', null],
            'publish: archived は不可' => [PublishAction::class, 'archived', null],
            'archive: published → archived' => [ArchiveAction::class, 'published', 'archived'],
            'archive: draft は不可' => [ArchiveAction::class, 'draft', null],
            'archive: archived は不可' => [ArchiveAction::class, 'archived', null],
            'unarchive: archived → draft' => [UnarchiveAction::class, 'archived', 'draft'],
            'unarchive: draft は不可' => [UnarchiveAction::class, 'draft', null],
            'unarchive: published は不可' => [UnarchiveAction::class, 'published', null],
        ];
    }
}
