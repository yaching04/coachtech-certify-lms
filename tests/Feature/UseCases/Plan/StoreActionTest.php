<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Models\User;
use App\UseCases\Plan\StoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_plan_as_draft_with_admin_as_creator(): void
    {
        $admin = User::factory()->admin()->create();

        $result = (new StoreAction)($admin, [
            'name' => '2 ヶ月プラン',
            'duration_days' => 60,
            'default_meeting_quota' => 2,
        ]);

        $this->assertSame(PlanStatus::Draft, $result->status);
        $this->assertSame($admin->id, $result->created_by_user_id);
        $this->assertSame($admin->id, $result->updated_by_user_id);
        $this->assertNull($result->description);
        $this->assertSame(0, $result->sort_order);
    }
}
