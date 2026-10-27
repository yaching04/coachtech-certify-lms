<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Dashboard;

use App\Enums\EnrollmentStatus;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\LearningSession;
use App\Models\User;
use App\UseCases\Dashboard\FetchCoachDashboardAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * T-B-01: コーチダッシュボードの担当受講生一覧が、担当受講生の人数に比例してクエリを増やさないこと、
 * かつ一覧の表示内容(受講生氏名 / 担当資格名 / 最終活動日時)・並び順・絞り込みが改修前と変わらないことを検証する。
 */
class CoachAssignedStudentsQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_count_does_not_grow_with_number_of_assigned_students(): void
    {
        [$coach, $cert] = $this->coachWithCertification();
        $this->createStudentsWithSessions($cert, 2);
        $this->actingAs($coach);

        $baseline = $this->countQueriesFor(fn () => $this->get(route('dashboard.index'))->assertOk());

        $this->createStudentsWithSessions($cert, 10);
        $scaled = $this->countQueriesFor(fn () => $this->get(route('dashboard.index'))->assertOk());

        $this->assertSame(
            $baseline,
            $scaled,
            "担当受講生が 2 名 → 12 名に増えたのにクエリ数が変わった (基準 {$baseline} → 増加後 {$scaled})。N+1 が再発していないか確認",
        );
    }

    public function test_action_query_count_is_constant_regardless_of_student_count(): void
    {
        [$coach, $cert] = $this->coachWithCertification();
        $this->createStudentsWithSessions($cert, 1);
        $action = app(FetchCoachDashboardAction::class);

        $one = $this->countQueriesFor(fn () => $action($coach));

        $this->createStudentsWithSessions($cert, 14);
        $fifteen = $this->countQueriesFor(fn () => $action($coach));

        $this->assertSame($one, $fifteen, "Action のクエリ数が受講生数で変わった (1 名 {$one} → 15 名 {$fifteen})");
    }

    public function test_list_shows_name_certification_and_latest_activity_for_each_row(): void
    {
        [$coach, $cert] = $this->coachWithCertification();
        $withSessions = Enrollment::factory()->for($cert)->learning()->create();
        $noSessions = Enrollment::factory()->for($cert)->learning()->create();

        // 最終活動日時は「最も新しい started_at」。古いセッションが複数あっても最新が使われる。
        LearningSession::factory()->forEnrollment($withSessions)->forUser($withSessions->user)->closed()
            ->startedOn(now()->subDays(10))->create();
        LearningSession::factory()->forEnrollment($withSessions)->forUser($withSessions->user)->closed()
            ->startedOn(now()->subDays(3))->create();
        LearningSession::factory()->forEnrollment($withSessions)->forUser($withSessions->user)->closed()
            ->startedOn(now()->subDays(7))->create();

        $vm = app(FetchCoachDashboardAction::class)($coach);

        $rowWith = $vm->assignedEnrollments->firstWhere('id', $withSessions->id);
        $rowWithout = $vm->assignedEnrollments->firstWhere('id', $noSessions->id);
        $this->assertSame(
            now()->subDays(3)->toDateString(),
            Carbon::parse((string) $rowWith->last_activity_at)->toDateString(),
        );
        $this->assertNull($rowWithout->last_activity_at);
        $this->assertTrue($rowWith->relationLoaded('user'));
        $this->assertTrue($rowWith->relationLoaded('certification'));

        $this->actingAs($coach)
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertSeeInOrder([$withSessions->user->name, $cert->name, '3日前'])
            ->assertSeeInOrder([$noSessions->user->name, $cert->name, '記録なし']);
    }

    public function test_order_and_filter_are_unchanged(): void
    {
        [$coach, $cert] = $this->coachWithCertification();
        $otherCert = Certification::factory()->published()->create();

        $learning = Enrollment::factory()->for($cert)->learning()->create();
        $passed = Enrollment::factory()->for($cert)->passed()->create(['passed_at' => now()]);
        $failed = Enrollment::factory()->for($cert)->failed()->create();
        $notMine = Enrollment::factory()->for($otherCert)->learning()->create();

        // 改修前と同じ条件・同じ並び(ソート指定なし)で取得した結果と、ID の並びが一致する。
        $expectedIds = Enrollment::query()
            ->whereIn('certification_id', $coach->coachingCertificationIds())
            ->whereIn('status', [EnrollmentStatus::Learning, EnrollmentStatus::Passed])
            ->pluck('id')
            ->all();

        $vm = app(FetchCoachDashboardAction::class)($coach);

        $this->assertSame($expectedIds, $vm->assignedEnrollments->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$learning->id, $passed->id], $vm->assignedEnrollments->pluck('id')->all());
        $this->assertNotContains($failed->id, $vm->assignedEnrollments->pluck('id')->all());
        $this->assertNotContains($notMine->id, $vm->assignedEnrollments->pluck('id')->all());
    }

    /**
     * @return array{0: User, 1: Certification}
     */
    private function coachWithCertification(): array
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        $cert->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);

        return [$coach, $cert];
    }

    private function createStudentsWithSessions(Certification $cert, int $count): void
    {
        Enrollment::factory()->for($cert)->learning()->count($count)->create()
            ->each(function (Enrollment $enrollment): void {
                LearningSession::factory()->forEnrollment($enrollment)->forUser($enrollment->user)->closed()
                    ->startedOn(now()->subDays(2))->create();
            });
    }

    private function countQueriesFor(\Closure $closure): int
    {
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });

        $closure();

        return $count;
    }
}
