<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateProfileRequest;
use App\UseCases\Settings\UpdateProfileAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * プロフィール設定 Controller(全ロール共通・本人のみ)。
 *
 * 対象は常にログイン中のユーザー自身(URL にユーザー ID を含まない)ため、他人の情報は構造的に更新できない。
 * タブ切替(プロフィール / パスワード)は同一画面内の `?tab=` クエリで行う。卒業済み受講生も利用できる
 * (`active-learning` ミドルウェアは付けない)。
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.profile', [
            'user' => $request->user(),
        ]);
    }

    public function update(UpdateProfileRequest $request, UpdateProfileAction $action): RedirectResponse
    {
        $action($request->user(), $request->validated());

        return redirect()
            ->route('settings.profile.edit')
            ->with('success', 'プロフィールを更新しました。');
    }
}
