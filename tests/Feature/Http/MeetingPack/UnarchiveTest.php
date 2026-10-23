<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UnarchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_unarchives_archived_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->archived()->create();

        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.unarchive', $plan));

        $response->assertRedirect(route('admin.meeting-packs.show', $plan));
        $response->assertSessionHas('success');
        $this->assertSame('draft', $plan->fresh()->status->value);
        $this->assertSame($admin->id, $plan->fresh()->updated_by_user_id);
    }

    #[DataProvider('invalidFromStatuses')]
    public function test_cannot_unarchive_from_invalid_status(string $status): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->{$status}()->create();

        $response = $this->actingAs($admin)->postJson(route('admin.meeting-packs.unarchive', $plan));

        $response->assertStatus(409);
        $response->assertJson(['message' => 'アーカイブ済みの面談パックのみ下書きへ戻せます。']);
        $this->assertSame($status, $plan->fresh()->status->value);
    }

    public function test_coach_and_student_cannot_unarchive(): void
    {
        $plan = MeetingPack::factory()->archived()->create();

        foreach ([User::factory()->coach()->create(), User::factory()->student()->create()] as $user) {
            $this->actingAs($user)
                ->post(route('admin.meeting-packs.unarchive', $plan))
                ->assertForbidden();
        }

        $this->assertSame('archived', $plan->fresh()->status->value);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidFromStatuses(): array
    {
        return [
            'draft からは不可' => ['draft'],
            'published からは不可' => ['published'],
        ];
    }
}
