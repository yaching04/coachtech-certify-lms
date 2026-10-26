<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdatePasswordRequest;
use App\UseCases\Settings\UpdatePasswordAction;
use Illuminate\Http\RedirectResponse;

/**
 * パスワード変更 Controller(全ロール共通・本人のみ)。現在のパスワードの確認は UpdatePasswordRequest で行う。
 */
class PasswordController extends Controller
{
    public function update(UpdatePasswordRequest $request, UpdatePasswordAction $action): RedirectResponse
    {
        $action($request->user(), $request->validated('password'));

        return redirect()
            ->route('settings.profile.edit', ['tab' => 'password'])
            ->with('success', 'パスワードを変更しました。');
    }
}
