<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 運営お知らせの配信対象タイプ。
 *
 * - AllStudents: 受講中の全受講生
 * - Certification: 指定資格を「学習中」で受講登録している受講中の受講生(修了・学習中止は対象外)
 * - User: 指定した受講中の受講生 1 名
 */
enum AnnouncementTargetType: string
{
    case AllStudents = 'all_students';
    case Certification = 'certification';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::AllStudents => '全受講生',
            self::Certification => '資格指定',
            self::User => 'ユーザー指定',
        };
    }
}
