<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Database\Seeder;

class QaThreadSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::where('email', 'student@certify-lms.test')->first();
        $coach = User::where('email', 'coach@certify-lms.test')->first();

        if (! $student || ! $coach) {
            return;
        }

        $certifications = Certification::where('status', CertificationStatus::Published)->get();

        foreach ($certifications as $certification) {
            // 未解決スレッド
            $thread1 = QaThread::create([
                'certification_id' => $certification->id,
                'user_id' => $student->id,
                'title' => $certification->name.' についての質問（未解決）',
                'body' => 'この資格の学習でつまずいている点があります。誰か教えてください。',
                'status' => QaThreadStatus::Unresolved,
            ]);

            // 回答あり
            QaReply::create([
                'qa_thread_id' => $thread1->id,
                'user_id' => $coach->id,
                'body' => 'ご質問ありがとうございます。該当箇所は教材の第2章を確認してみてください。',
            ]);

            // 解決済スレッド
            QaThread::create([
                'certification_id' => $certification->id,
                'user_id' => $student->id,
                'title' => $certification->name.' についての質問（解決済）',
                'body' => '以前わからなかった点が解決しました。ありがとうございました。',
                'status' => QaThreadStatus::Resolved,
                'resolved_at' => now()->subDays(2),
            ]);

            // 回答なしの未解決
            QaThread::create([
                'certification_id' => $certification->id,
                'user_id' => $student->id,
                'title' => $certification->name.' の未回答質問',
                'body' => 'まだ誰も回答していない質問です。',
                'status' => QaThreadStatus::Unresolved,
            ]);
        }
    }
}
