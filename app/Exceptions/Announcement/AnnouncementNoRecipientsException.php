<?php

declare(strict_types=1);

namespace App\Exceptions\Announcement;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 配信対象に該当する受講中の受講生が 0 名のときに配信を拒否する(配信履歴も作らない)。
 */
final class AnnouncementNoRecipientsException extends UnprocessableEntityHttpException
{
    public function __construct(
        string $message = '配信対象に該当する受講中の受講生がいないため、配信できませんでした。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
