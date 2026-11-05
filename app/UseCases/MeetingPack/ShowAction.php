<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Models\MeetingPack;

/**
 * admin 用の面談パック詳細を取得するユースケース。
 * メタ情報表示用に作成者 / 最終更新者を Eager Loading で揃える。
 *
 * 購入履歴(Payment)は追加面談購入 Feature 所有のモデルで、本 Feature 時点では未導入のため読み込まない
 * (詳細画面側は `class_exists(Payment::class)` で未導入時に空状態を表示する)。
 */
final class ShowAction
{
    public function __invoke(MeetingPack $plan): MeetingPack
    {
        return $plan->load(['createdBy', 'updatedBy']);
    }
}
