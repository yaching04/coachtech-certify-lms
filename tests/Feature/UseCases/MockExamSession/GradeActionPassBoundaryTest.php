<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\MockExamSession;

use App\Models\MockExam;
use App\Models\MockExamAnswer;
use App\Models\MockExamQuestion;
use App\Models\MockExamSession;
use App\UseCases\MockExamSession\GradeAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B-B-05: 合格点(1〜100 の百分率)と得点率(0〜100 の百分率)の単位が揃っていること、
 * 境界(合格点ちょうど・満点・合格点未満)で正しく合否判定されることを確認する。
 */
class GradeActionPassBoundaryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{int, int, int, bool}>
     */
    public static function boundaryProvider(): array
    {
        return [
            '合格点 100 で満点なら合格' => [100, 4, 4, true],
            '合格点 100 で 1 問ミスなら不合格' => [100, 4, 3, false],
            '合格点ちょうど(60%)なら合格' => [60, 5, 3, true],
            '合格点 1 つ下(40% < 60%)なら不合格' => [60, 5, 2, false],
            '合格点 1 で 1 問でも正解なら合格' => [1, 10, 1, true],
            '全問不正解は不合格' => [1, 4, 0, false],
        ];
    }

    /**
     * @dataProvider boundaryProvider
     */
    public function test_pass_flag_follows_passing_score(int $passingScore, int $total, int $correct, bool $expectedPass): void
    {
        $mockExam = MockExam::factory()->published()->passingScore($passingScore)->create();
        $questions = collect();
        for ($i = 0; $i < $total; $i++) {
            $questions->push(MockExamQuestion::factory()->forMockExam($mockExam)->withOptions(4, 0)->create(['order' => $i]));
        }

        $session = MockExamSession::factory()
            ->forMockExam($mockExam)
            ->inProgress()
            ->create([
                'generated_question_ids' => $questions->pluck('id')->all(),
                'total_questions' => $total,
                'passing_score_snapshot' => $passingScore,
            ]);

        foreach ($questions as $index => $question) {
            $selected = $index < $correct
                ? $question->options->firstWhere('is_correct', true)
                : $question->options->firstWhere('is_correct', false);

            MockExamAnswer::factory()->create([
                'mock_exam_session_id' => $session->id,
                'mock_exam_question_id' => $question->id,
                'selected_option_id' => $selected->id,
                'selected_option_body' => $selected->body,
                'is_correct' => false,
                'answered_at' => now(),
            ]);
        }

        (app(GradeAction::class))($session);

        $session->refresh();
        $this->assertEquals(round($correct / $total * 100, 2), (float) $session->score_percentage);
        $this->assertSame($expectedPass, $session->pass);
    }
}
