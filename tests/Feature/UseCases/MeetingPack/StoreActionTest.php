<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\User;
use App\UseCases\MeetingPack\StoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_meeting_pack_as_draft_with_admin_as_creator(): void
    {
        $admin = User::factory()->admin()->create();

        $result = (new StoreAction)($admin, [
            'name' => '2 回パック',
            'meeting_count' => 2,
            'price' => 5800,
        ]);

        $this->assertSame(MeetingPackStatus::Draft, $result->status);
        $this->assertSame($admin->id, $result->created_by_user_id);
        $this->assertSame($admin->id, $result->updated_by_user_id);
        $this->assertNull($result->description);
        $this->assertNull($result->stripe_price_id);
        $this->assertSame(0, $result->sort_order);
    }
}
