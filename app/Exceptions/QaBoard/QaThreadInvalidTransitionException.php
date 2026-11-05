<?php

declare(strict_types=1);

namespace App\Exceptions\QaBoard;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 質問スレッドの解決状態の切替が不正な場合の例外(HTTP 409)。
 * 解決済のスレッドを再度解決済にする / 未解決のスレッドを未解決に戻す操作で throw する。
 */
final class QaThreadInvalidTransitionException extends ConflictHttpException
{
    public static function forResolve(): self
    {
        return new self('未解決の質問のみ解決済にできます。');
    }

    public static function forUnresolve(): self
    {
        return new self('解決済の質問のみ未解決に戻せます。');
    }
}
