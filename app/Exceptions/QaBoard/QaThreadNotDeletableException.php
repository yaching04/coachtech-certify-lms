<?php

declare(strict_types=1);

namespace App\Exceptions\QaBoard;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 回答が付いた質問スレッドを投稿者本人が削除しようとした際の例外(HTTP 409)。
 * `QaThread\DestroyAction` が「回答が 1 件以上付いたスレッドは本人削除不可(管理者のモデレーション削除は可)」の
 * ドメインルールから throw する。
 */
final class QaThreadNotDeletableException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('回答が付いた質問は削除できません。', $previous);
    }
}
