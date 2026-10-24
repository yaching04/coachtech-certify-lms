<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_archives_published_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->published()->create();

        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.archive', $plan));

        $response->assertRedirect(route('admin.meeting-packs.show', $plan));
        $response->assertSessionHas('success');
        $this->assertSame('archived', $plan->fresh()->status->value);
        $this->assertSame($admin->id, $plan->fresh()->updated_by_user_id);
    }

    #[DataProvider('invalidFromStatuses')]
    public function test_cannot_archive_from_invalid_status(string $status): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->{$status}()->create();

        $response = $this->actingAs($admin)->postJson(route('admin.meeting-packs.archive', $plan));

        $response->assertStatus(409);
        $response->assertJson(['message' => '公開中の面談パックのみアーカイブできます。']);
        $this->assertSame($status, $plan->fresh()->status->value);
    }

    public function test_coach_and_student_cannot_archive(): void
    {
        $plan = MeetingPack::factory()->published()->create();

        foreach ([User::factory()->coach()->create(), User::factory()->student()->create()] as $user) {
            $this->actingAs($user)
                ->post(route('admin.meeting-packs.archive', $plan))
                ->assertForbidden();
        }

        $this->assertSame('published', $plan->fresh()->status->value);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidFromStatuses(): array
    {
        return [
            'draft からは不可' => ['draft'],
            'archived からは不可' => ['archived'],
        ];
    }
}
