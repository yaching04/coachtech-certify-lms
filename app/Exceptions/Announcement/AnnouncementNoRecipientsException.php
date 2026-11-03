<?php

declare(strict_types=1);

namespace App\Exceptions\Announcement;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 配信対象に該当する受講中の受講生が 0 名のときに配信を拒否する(配信履歴も作らない)。
 * 資格指定では「指定資格を学習中の受講生」が 0 名のとき(修了・学習中止の受講生しかいない場合も含む)。
 */
final class AnnouncementNoRecipientsException extends UnprocessableEntityHttpException
{
    public function __construct(
        string $message = '配信対象に該当する受講中の受講生がいないため、配信できませんでした。（資格指定の場合は、指定資格を「学習中」で受講登録している受講生が対象です）',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
