<?php

declare(strict_types=1);

namespace App\Exceptions\Plan;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 削除条件を満たさないプランを削除しようとした際の例外(HTTP 409)。
 * `Plan\DestroyAction` が「下書きかつ受講者・プラン履歴が紐づかないプランのみ削除可」のドメインルールから throw する。
 * バリエーションごとに static factory(`notDraft` / `hasUsers`)でメッセージを生成する。
 */
final class PlanNotDeletableException extends ConflictHttpException
{
    public static function notDraft(): self
    {
        return new self('下書き以外のプランは削除できません。公開中のプランはアーカイブしてください。');
    }

    public static function hasUsers(): self
    {
        return new self('受講者(または受講履歴)が紐づいているプランは削除できません。');
    }

    private function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
