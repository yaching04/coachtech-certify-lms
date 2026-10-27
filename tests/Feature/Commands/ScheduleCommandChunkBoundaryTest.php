<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\EnrollmentStatus;
use App\Enums\MeetingStatus;
use App\Enums\UserStatus;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * T-B-02: 定期実行コマンドの分割処理(chunkById)の境界を検証する。
 * 件数がチャンクサイズ(100)の整数倍 / 複数チャンクにまたがる場合でも全件を処理し、対象外は触らず、
 * 1 回に読み込む件数が 100 件に固定され、offset 方式(取りこぼしの原因)になっていないことを確認する。
 */
class ScheduleCommandChunkBoundaryTest extends TestCase
{
    use RefreshDatabase;

    /** 3 チャンクにまたがる件数(100 + 100 + 50)。 */
    private const COUNT = 250;

    public function test_graduate_expired_users_processes_every_target_and_ignores_others(): void
    {
        $plan = Plan::factory()->published()->create();
        User::factory()->inProgress()->withPlan($plan)->count(self::COUNT)->create([
            'plan_expires_at' => now()->subDay(),
        ]);
        $notExpired = User::factory()->inProgress()->withPlan($plan)->count(3)->create([
            'plan_expires_at' => now()->addDay(),
        ]);

        $this->artisan('users:graduate-expired')
            ->expectsOutput('Graduated '.self::COUNT.' expired users.')
            ->assertExitCode(0);

        $this->assertSame(self::COUNT, User::query()->where('status', UserStatus::Graduated->value)->count());
        $this->assertSame(
            3,
            User::query()->whereIn('id', $notExpired->pluck('id'))->where('status', UserStatus::InProgress->value)->count(),
        );
    }

    public function test_graduate_expired_users_with_exact_multiple_of_chunk_size(): void
    {
        $plan = Plan::factory()->published()->create();
        User::factory()->inProgress()->withPlan($plan)->count(200)->create([
            'plan_expires_at' => now()->subDay(),
        ]);

        $this->artisan('users:graduate-expired')->assertExitCode(0);

        $this->assertSame(200, User::query()->where('status', UserStatus::Graduated->value)->count());
    }

    public function test_fail_expired_enrollments_processes_every_target_and_ignores_others(): void
    {
        Enrollment::factory()->learning()->count(self::COUNT)->create([
            'exam_date' => now()->subDay()->toDateString(),
        ]);
        $future = Enrollment::factory()->learning()->count(3)->create([
            'exam_date' => now()->addDays(10)->toDateString(),
        ]);
        $noExamDate = Enrollment::factory()->learning()->create(['exam_date' => null]);

        $this->artisan('enrollments:fail-expired')
            ->expectsOutput('Failed '.self::COUNT.' expired enrollments.')
            ->assertExitCode(0);

        $this->assertSame(self::COUNT, Enrollment::query()->where('status', EnrollmentStatus::Failed->value)->count());
        $this->assertSame(
            4,
            Enrollment::query()
                ->whereIn('id', $future->pluck('id')->push($noExamDate->id))
                ->where('status', EnrollmentStatus::Learning->value)
                ->count(),
        );
    }

    public function test_auto_complete_meetings_processes_every_target_and_ignores_others(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->learning()->for($student, 'user')->create();

        // UNIQUE(coach_id, scheduled_at) 回避のため、全件を別々の時刻に置く(過去 = 60 分超過 / 未来 = 対象外)。
        $base = now()->copy()->startOfHour()->subHours(2);
        for ($i = 0; $i < self::COUNT; $i++) {
            Meeting::factory()->reserved()
                ->forCoach($coach)->forStudent($student)->forEnrollment($enrollment)
                ->create(['scheduled_at' => $base->copy()->subHours($i)]);
        }
        $future = Meeting::factory()->reserved()
            ->forCoach($coach)->forStudent($student)->forEnrollment($enrollment)
            ->create(['scheduled_at' => now()->addDay()->startOfHour()]);

        $this->artisan('meetings:auto-complete')->assertExitCode(0);

        $this->assertSame(self::COUNT, Meeting::query()->where('status', MeetingStatus::Completed->value)->count());
        $this->assertSame(MeetingStatus::Reserved, $future->fresh()->status);
    }

    public function test_commands_read_fixed_100_rows_per_query_without_offset(): void
    {
        $plan = Plan::factory()->published()->create();
        User::factory()->inProgress()->withPlan($plan)->count(self::COUNT)->create([
            'plan_expires_at' => now()->subDay(),
        ]);

        $selects = [];
        DB::listen(function ($query) use (&$selects): void {
            if (str_starts_with($query->sql, 'select') && str_contains($query->sql, 'plan_expires_at')) {
                $selects[] = $query->sql;
            }
        });

        $this->artisan('users:graduate-expired')->assertExitCode(0);

        // 250 件 = 100 + 100 + 50 の 3 回で読み切る(最後のチャンクが 100 件未満なので、そこで終了)。
        $this->assertCount(3, $selects);
        foreach ($selects as $sql) {
            $this->assertStringContainsString('limit 100', $sql);
            $this->assertStringNotContainsString('offset', $sql);
        }
    }
}
