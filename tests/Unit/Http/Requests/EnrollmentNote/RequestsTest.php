<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\EnrollmentNote;

use App\Http\Requests\EnrollmentNote\StoreRequest;
use App\Http\Requests\EnrollmentNote\UpdateRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * コーチメモ StoreRequest / UpdateRequest の本文バリデーション検証(必須・2000 文字以内・文字列)。
 */
class RequestsTest extends TestCase
{
    /**
     * @return array<string, array{0: class-string, 1: array<string, mixed>, 2: bool}>
     */
    public static function cases(): array
    {
        $cases = [];
        foreach (['store' => StoreRequest::class, 'update' => UpdateRequest::class] as $name => $class) {
            $cases["{$name}: 1 char"] = [$class, ['body' => 'あ'], true];
            $cases["{$name}: 2000 chars"] = [$class, ['body' => str_repeat('あ', 2000)], true];
            $cases["{$name}: multi line"] = [$class, ['body' => "1 行目\n2 行目"], true];
            $cases["{$name}: missing"] = [$class, [], false];
            $cases["{$name}: empty"] = [$class, ['body' => ''], false];
            $cases["{$name}: null"] = [$class, ['body' => null], false];
            $cases["{$name}: 2001 chars"] = [$class, ['body' => str_repeat('あ', 2001)], false];
            $cases["{$name}: array"] = [$class, ['body' => ['x']], false];
        }

        return $cases;
    }

    #[DataProvider('cases')]
    public function test_body_rules(string $class, array $payload, bool $passes): void
    {
        $request = new $class;

        $validator = Validator::make($payload, $request->rules(), [], $request->attributes());

        $this->assertSame($passes, $validator->passes());
    }
}
