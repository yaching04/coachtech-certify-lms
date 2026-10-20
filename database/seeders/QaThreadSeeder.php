<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Database\Seeder;

class QaThreadSeeder extends Seeder
{
    public function run(): void
    {
        $fixedStudent = User::where('email', 'student@certify-lms.test')->first();
        $coach = User::where('email', 'coach@certify-lms.test')->first();

        if (! $fixedStudent || ! $coach) {
            return;
        }

        // 他人の投稿用に別の受講生を用意
        $otherStudent = User::factory()->create([
            'role' => UserRole::Student,
            'name' => '他の受講生',
        ]);

        $publishedCerts = Certification::where('status', CertificationStatus::Published)->get();
        $unpublishedCert = Certification::where('status', '!=', CertificationStatus::Published)->first();

        foreach ($publishedCerts as $certIndex => $certification) {
            // === 固定受講生の投稿 ===
            // 回答0件・未解決
            QaThread::create([
                'certification_id' => $certification->id,
                'user_id' => $fixedStudent->id,
                'title' => "{$certification->name} の進め方について",
                'body' => 'この資格の学習を効率よく進める方法を教えてください。',
                'status' => QaThreadStatus::Unresolved,
                'created_at' => now()->subDays(6 - $certIndex),
            ]);

            // 回答2件・未解決
            $thread1 = QaThread::create([
                'certification_id' => $certification->id,
                'user_id' => $fixedStudent->id,
                'title' => "{$certification->name} の過去問の解き方",
                'body' => '過去問を解くときに意識すべきポイントはありますか？',
                'status' => QaThreadStatus::Unresolved,
                'created_at' => now()->subDays(4),
            ]);
            QaReply::create(['qa_thread_id' => $thread1->id, 'user_id' => $coach->id, 'body' => '時間を測って解くことをおすすめします。']);
            QaReply::create(['qa_thread_id' => $thread1->id, 'user_id' => $otherStudent->id, 'body' => '自分も同じところでつまずきました。']);

            // 解決済み
            $resolved = QaThread::create([
                'certification_id' => $certification->id,
                'user_id' => $fixedStudent->id,
                'title' => "{$certification->name} の用語確認",
                'body' => '以前わからなかった用語が理解できました。',
                'status' => QaThreadStatus::Resolved,
                'resolved_at' => now()->subDays(2),
                'created_at' => now()->subDays(5),
            ]);
            QaReply::create(['qa_thread_id' => $resolved->id, 'user_id' => $fixedStudent->id, 'body' => '自己解決しました。']);

            // === 他人の投稿 ===
            // 回答0件
            QaThread::create([
                'certification_id' => $certification->id,
                'user_id' => $otherStudent->id,
                'title' => "{$certification->name} の難易度について",
                'body' => 'この資格は初心者には難しいですか？',
                'status' => QaThreadStatus::Unresolved,
                'created_at' => now()->subDays(3),
            ]);

            // 回答1件
            $otherThread = QaThread::create([
                'certification_id' => $certification->id,
                'user_id' => $otherStudent->id,
                'title' => "{$certification->name} の勉強時間について",
                'body' => '合格までにどのくらいの時間が必要でしたか？',
                'status' => QaThreadStatus::Unresolved,
                'created_at' => now()->subDays(2),
            ]);
            QaReply::create(['qa_thread_id' => $otherThread->id, 'user_id' => $coach->id, 'body' => '個人差はありますが、200〜300時間程度が目安です。']);
        }

        // 公開停止中の資格（管理者確認用）
        if ($unpublishedCert) {
            QaThread::create([
                'certification_id' => $unpublishedCert->id,
                'user_id' => $fixedStudent->id,
                'title' => '公開停止中の資格に関する質問',
                'body' => 'このスレッドは管理者の画面でのみ見えるはずです。',
                'status' => QaThreadStatus::Unresolved,
                'created_at' => now()->subDay(),
            ]);
        }

        // ページネーション確認用（12件超になるように追加）
        if ($publishedCerts->isNotEmpty()) {
            $cert = $publishedCerts->first();
            for ($i = 1; $i <= 8; $i++) {
                $poster = $i % 2 === 0 ? $fixedStudent : $otherStudent;
                QaThread::create([
                    'certification_id' => $cert->id,
                    'user_id' => $poster->id,
                    'title' => "追加の質問 {$i}",
                    'body' => "ページネーションと新着順を確認するためのスレッドです。",
                    'status' => $i % 4 === 0 ? QaThreadStatus::Resolved : QaThreadStatus::Unresolved,
                    'resolved_at' => $i % 4 === 0 ? now()->subHours($i) : null,
                    'created_at' => now()->subHours(15 - $i),
                ]);
            }
        }
    }
}
