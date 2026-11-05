<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

/**
 * 開発用 質問掲示板(qa-board)シーダー。
 *
 * 1. **公開中の資格ごとに 3 スレッド**: 未解決・回答 0 件(固定 student 投稿)/ 解決済・回答 2 件(固定 student 投稿)/
 *    未解決・回答 1 件(別受講生投稿、固定 student が回答)を作成日時をずらして投入する。
 *    「自分の質問」の編集 / 削除(回答 0 件のみ可)/ 解決マーク、絞り込み、並び順の動作を確認できる。
 * 2. **ページネーション確認用**: 先頭の公開中資格に古い日付のスレッドを追加し、受講生の一覧が 2 ページ目まで続くようにする。
 * 3. **公開停止中資格のスレッド**: 管理者モデレーション画面(/admin/qa-board)だけに表示されることを確認する用。
 *
 * 回答者のコーチは当該資格の担当コーチ(未割当なら coach@certify-lms.test)。
 * 依存順序: `UserSeeder` → `CertificationSeeder`(担当コーチ割当含む)→ 本 Seeder。
 */
final class QaThreadSeeder extends Seeder
{
    private const PAGINATION_EXTRA_THREADS = 10;

    public function run(): void
    {
        $student = User::where('email', 'student@certify-lms.test')->first();
        $otherStudent = User::where('email', 'student-noquota@certify-lms.test')->first();
        $fallbackCoach = User::where('email', 'coach@certify-lms.test')->first();

        if ($student === null || $otherStudent === null || $fallbackCoach === null) {
            $this->command?->warn('QaThreadSeeder: 固定アカウントが存在しません。先に UserSeeder を実行してください。');

            return;
        }

        $published = Certification::query()
            ->where('status', CertificationStatus::Published->value)
            ->with('coaches')
            ->orderBy('created_at')
            ->get();

        if ($published->isEmpty()) {
            $this->command?->warn('QaThreadSeeder: 公開中の資格が存在しません。先に CertificationSeeder を実行してください。');

            return;
        }

        foreach ($published->values() as $index => $certification) {
            $coach = $certification->coaches->first() ?? $fallbackCoach;
            $this->seedCertificationThreads($certification, $student, $otherStudent, $coach, $index);
        }

        $this->seedPaginationThreads($published->first(), $otherStudent);
        $this->seedUnpublishedThread($student);
    }

    private function seedCertificationThreads(
        Certification $certification,
        User $student,
        User $otherStudent,
        User $coach,
        int $offset,
    ): void {
        $base = now()->subHours($offset * 7);

        // 1. 未解決・回答 0 件(固定 student の質問 = 編集 / 削除 / 解決マークの動線確認用)
        $this->createThread(
            $certification,
            $student,
            "【{$certification->name}】過去問の解説で分からない箇所があります",
            "過去問を解いていて、解説の途中の考え方が理解できませんでした。\nどのような順序で考えればよいか教えていただけますか。",
            QaThreadStatus::Open,
            $base->copy()->subHours(2),
        );

        // 2. 解決済・回答 2 件(固定 student の質問)
        $resolved = $this->createThread(
            $certification,
            $student,
            "【{$certification->name}】おすすめの学習順序について",
            "教材をどの順番で進めるのが効率的でしょうか。\n苦手分野から始めるべきか、最初から順に進めるべきか迷っています。",
            QaThreadStatus::Resolved,
            $base->copy()->subDays(5),
        );
        $this->createReply($resolved, $coach, 'まずは全体を一通り進めて、模試で苦手分野を把握してから重点的に復習するのがおすすめです。', $base->copy()->subDays(5)->addHours(3));
        $this->createReply($resolved, $otherStudent, '私も同じ順序で進めて、2 周目で理解が深まりました。', $base->copy()->subDays(4));

        // 3. 未解決・回答 1 件(別受講生の質問に固定 student が回答)
        $other = $this->createThread(
            $certification,
            $otherStudent,
            "【{$certification->name}】試験当日の時間配分",
            '本番で時間が足りなくなるのが不安です。皆さんはどのように時間配分していますか?',
            QaThreadStatus::Open,
            $base->copy()->subDays(2),
        );
        $this->createReply($other, $student, '見直し時間を 10 分確保して、分からない問題は後回しにしています。', $base->copy()->subDays(1));
    }

    private function seedPaginationThreads(Certification $certification, User $author): void
    {
        for ($i = 1; $i <= self::PAGINATION_EXTRA_THREADS; $i++) {
            $this->createThread(
                $certification,
                $author,
                "【{$certification->name}】用語の意味について質問 {$i}",
                "教材 {$i} 章に出てくる用語の意味がよく分かりません。具体例があれば教えてください。",
                $i % 3 === 0 ? QaThreadStatus::Resolved : QaThreadStatus::Open,
                now()->subDays(10 + $i),
            );
        }
    }

    private function seedUnpublishedThread(User $student): void
    {
        $unpublished = Certification::query()
            ->where('status', '!=', CertificationStatus::Published->value)
            ->orderBy('created_at')
            ->first();

        if ($unpublished === null) {
            return;
        }

        $this->createThread(
            $unpublished,
            $student,
            "【{$unpublished->name}】公開停止前に投稿された質問",
            'この資格は現在公開停止中のため、受講生・コーチの一覧には表示されず、管理者のモデレーション画面にのみ表示されます。',
            QaThreadStatus::Open,
            now()->subDays(30),
        );
    }

    private function createThread(
        Certification $certification,
        User $author,
        string $title,
        string $body,
        QaThreadStatus $status,
        CarbonInterface $createdAt,
    ): QaThread {
        $thread = new QaThread([
            'certification_id' => $certification->id,
            'user_id' => $author->id,
            'title' => $title,
            'body' => $body,
            'status' => $status,
            'resolved_at' => $status === QaThreadStatus::Resolved ? $createdAt->copy()->addDay() : null,
        ]);
        $thread->created_at = $createdAt;
        $thread->updated_at = $createdAt;
        $thread->save();

        return $thread;
    }

    private function createReply(QaThread $thread, User $author, string $body, CarbonInterface $createdAt): void
    {
        $reply = new QaReply([
            'qa_thread_id' => $thread->id,
            'user_id' => $author->id,
            'body' => $body,
        ]);
        $reply->created_at = $createdAt;
        $reply->updated_at = $createdAt;
        $reply->save();
    }
}
