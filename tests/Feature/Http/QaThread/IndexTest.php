<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_sees_threads_of_published_certifications_only(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $published = Certification::factory()->published()->create();
        $draft = Certification::factory()->draft()->create();
        $visible = QaThread::factory()->forCertification($published)->create(['title' => '公開中資格の質問']);
        QaThread::factory()->forCertification($draft)->create(['title' => '非公開資格の質問']);

        $response = $this->actingAs($student)->get(route('qa-board.index'));

        $response->assertOk()
            ->assertViewIs('qa-thread.index')
            ->assertSee('公開中資格の質問')
            ->assertDontSee('非公開資格の質問');
        $this->assertSame([$visible->id], $response->viewData('threads')->pluck('id')->all());
    }

    public function test_coach_sees_threads_of_assigned_published_certifications_only(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $assigned = Certification::factory()->published()->create();
        $other = Certification::factory()->published()->create();
        $this->assignCoach($assigned, $coach, $admin);
        QaThread::factory()->forCertification($assigned)->create(['title' => '担当資格の質問']);
        QaThread::factory()->forCertification($other)->create(['title' => '担当外資格の質問']);

        $response = $this->actingAs($coach)->get(route('qa-board.index'));

        $response->assertOk()
            ->assertSee('担当資格の質問')
            ->assertDontSee('担当外資格の質問');
        $this->assertSame([$assigned->id], $response->viewData('certifications')->pluck('id')->all());
    }

    public function test_threads_are_ordered_newest_first_and_paginated_by_20(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        foreach (range(1, 21) as $i) {
            QaThread::factory()->forCertification($cert)->create(['created_at' => now()->subMinutes($i)]);
        }
        $newest = QaThread::factory()->forCertification($cert)->create(['created_at' => now()]);

        $response = $this->actingAs($student)->get(route('qa-board.index'));

        $threads = $response->viewData('threads');
        $this->assertSame(20, $threads->count());
        $this->assertSame(22, $threads->total());
        $this->assertSame($newest->id, $threads->first()->id);

        $this->assertSame(2, $this->actingAs($student)->get(route('qa-board.index', ['page' => 2]))->viewData('threads')->count());
    }

    public function test_filters_by_certification_status_and_keyword(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certA = Certification::factory()->published()->create();
        $certB = Certification::factory()->published()->create();
        $openA = QaThread::factory()->forCertification($certA)->open()->create(['title' => 'サブネットマスクの計算', 'body' => '本文']);
        $resolvedA = QaThread::factory()->forCertification($certA)->resolved()->create(['title' => '別の質問', 'body' => 'サブネットについて']);
        QaThread::factory()->forCertification($certB)->open()->create(['title' => 'サブネット B', 'body' => '本文']);

        $ids = fn (array $query) => $this->actingAs($student)
            ->get(route('qa-board.index', $query))
            ->assertOk()
            ->viewData('threads')->pluck('id')->sort()->values()->all();

        $this->assertEqualsCanonicalizing([$openA->id, $resolvedA->id], $ids(['certification_id' => $certA->id]));
        $this->assertSame([$openA->id], $ids(['certification_id' => $certA->id, 'status' => 'unresolved']));
        $this->assertSame([$resolvedA->id], $ids(['certification_id' => $certA->id, 'status' => 'resolved']));
        // キーワードはタイトル・本文の部分一致
        $this->assertEqualsCanonicalizing([$openA->id, $resolvedA->id], $ids(['certification_id' => $certA->id, 'keyword' => 'サブネット']));
        $this->assertSame([], $ids(['keyword' => '存在しない語']));
    }

    public function test_keyword_escapes_like_wildcards(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        $hit = QaThread::factory()->forCertification($cert)->create(['title' => '100%の理解', 'body' => '本文']);
        QaThread::factory()->forCertification($cert)->create(['title' => '100点の理解', 'body' => '本文']);

        $ids = $this->actingAs($student)->get(route('qa-board.index', ['keyword' => '100%']))
            ->viewData('threads')->pluck('id')->all();

        $this->assertSame([$hit->id], $ids);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)
            ->get(route('qa-board.index', ['status' => 'closed']))
            ->assertSessionHasErrors('status');
    }

    public function test_keyword_longer_than_100_is_rejected(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)
            ->get(route('qa-board.index', ['keyword' => str_repeat('あ', 101)]))
            ->assertSessionHasErrors('keyword');
    }

    public function test_list_query_count_does_not_grow_with_threads(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->create();
        QaReply::factory()->forThread($thread)->count(2)->create();

        $countQueries = function () use ($student): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            // サイドバー等がユーザーインスタンスにリレーションをキャッシュするため、毎回 fresh なインスタンスで計測する
            $this->actingAs($student->fresh())->get(route('qa-board.index'))->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $few = $countQueries();

        foreach (range(1, 10) as $_) {
            $t = QaThread::factory()->forCertification(Certification::factory()->published()->create())->create();
            QaReply::factory()->forThread($t)->count(2)->create();
        }

        $this->assertSame($few, $countQueries());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('qa-board.index'))->assertRedirect(route('login'));
    }

    public function test_admin_cannot_use_public_board(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('qa-board.index'))->assertForbidden();
    }

    private function assignCoach(Certification $cert, User $coach, User $admin): void
    {
        $cert->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
    }
}
