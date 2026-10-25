<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Plan;
use App\Models\User;
use App\Policies\PlanPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PlanPolicy の ability × Role マトリクス検証。
 * 全 ability (viewAny / view / create / update / delete / publish / archive / unarchive) が admin のみ true、
 * coach / student は false であることを、プランの全状態について網羅する。
 */
class PlanPolicyTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('roleMatrix')]
    public function test_all_abilities_follow_role(string $actingRole, bool $expected): void
    {
        // Arrange
        $actor = User::factory()->{$actingRole}()->create();
        $policy = new PlanPolicy;

        // Act & Assert
        $this->assertSame($expected, $policy->viewAny($actor), "{$actingRole} の viewAny");
        $this->assertSame($expected, $policy->create($actor), "{$actingRole} の create");

        foreach (['draft', 'published', 'archived'] as $status) {
            $plan = Plan::factory()->{$status}()->create();

            foreach (['view', 'update', 'delete', 'publish', 'archive', 'unarchive'] as $ability) {
                $this->assertSame(
                    $expected,
                    $policy->{$ability}($actor, $plan),
                    "{$actingRole} の {$ability}({$status}) は ".($expected ? 'true' : 'false').' を返すはず',
                );
            }
        }
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function roleMatrix(): array
    {
        return [
            'admin は全操作可' => ['admin', true],
            'coach は全操作不可' => ['coach', false],
            'student は全操作不可' => ['student', false],
        ];
    }
}
