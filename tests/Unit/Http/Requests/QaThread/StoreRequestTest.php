<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QaThread;

use App\Http\Requests\QaThread\StoreRequest;
use App\Models\Certification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * 質問スレッド投稿 StoreRequest の rules() を検証する Unit テスト。
 * certification_id(公開中のみ)/ title(max:200)/ body(max:5000)を網羅する。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_payload_at_max_length(): void
    {
        $cert = Certification::factory()->published()->create();

        $validator = Validator::make([
            'certification_id' => $cert->id,
            'title' => str_repeat('あ', 200),
            'body' => str_repeat('あ', 5000),
        ], (new StoreRequest)->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_over_max_length(): void
    {
        $cert = Certification::factory()->published()->create();

        $errors = Validator::make([
            'certification_id' => $cert->id,
            'title' => str_repeat('あ', 201),
            'body' => str_repeat('あ', 5001),
        ], (new StoreRequest)->rules())->errors()->toArray();

        $this->assertArrayHasKey('title', $errors);
        $this->assertArrayHasKey('body', $errors);
    }

    public function test_fails_when_certification_is_not_published(): void
    {
        foreach ([Certification::factory()->draft()->create(), Certification::factory()->archived()->create()] as $cert) {
            $errors = Validator::make([
                'certification_id' => $cert->id,
                'title' => 't',
                'body' => 'b',
            ], (new StoreRequest)->rules())->errors()->toArray();

            $this->assertArrayHasKey('certification_id', $errors);
        }
    }

    public function test_fails_when_required_fields_missing(): void
    {
        $errors = Validator::make([], (new StoreRequest)->rules())->errors()->toArray();

        $this->assertArrayHasKey('certification_id', $errors);
        $this->assertArrayHasKey('title', $errors);
        $this->assertArrayHasKey('body', $errors);
    }
}
