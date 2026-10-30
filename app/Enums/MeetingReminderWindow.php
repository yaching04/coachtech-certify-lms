<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * 面談リマインダーの配信タイミング。notifications:send-meeting-reminders --window={value} で指定する。
 *
 * - eve: 前日リマインダー。毎日 1 回起動し、翌日(アプリのタイムゾーンで翌日 0:00〜24:00)に開始する面談が対象
 * - one_hour_before: 開始 1 時間前リマインダー。15 分間隔で起動し、開始時刻が「今 〜 60 分後」の面談が対象
 */
enum MeetingReminderWindow: string
{
    case Eve = 'eve';
    case OneHourBefore = 'one_hour_before';

    public function label(): string
    {
        return match ($this) {
            self::Eve => '前日',
            self::OneHourBefore => '1 時間前',
        };
    }

    /**
     * 対象とする面談開始時刻の範囲 [from, to]。from は含まず(one_hour_before の「開始済み」を除外するため)、to は含む。
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function range(CarbonInterface $now): array
    {
        $now = CarbonImmutable::instance($now);

        return match ($this) {
            self::Eve => [
                $now->addDay()->startOfDay()->subSecond(),
                $now->addDay()->endOfDay(),
            ],
            self::OneHourBefore => [$now, $now->addMinutes(60)],
        };
    }
}
