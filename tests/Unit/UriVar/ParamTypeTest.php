<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Tests\Unit\UriVar;

use PHPUnit\Framework\TestCase;
use Speto\ApiPlatformInvokerBundle\UriVar\ParamType;

final class ParamTypeTest extends TestCase
{
    /**
     * @dataProvider acceptsProvider
     */
    public function testAccepts(\ReflectionParameter $parameter, mixed $value, bool $expected): void
    {
        $result = ParamType::accepts($parameter, $value);

        self::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{0: \ReflectionParameter, 1: mixed, 2: bool}>
     */
    public static function acceptsProvider(): iterable
    {
        $stringParam = self::getParameter(fn (string $p) => null);
        $intParam = self::getParameter(fn (int $p) => null);
        $floatParam = self::getParameter(fn (float $p) => null);
        $boolParam = self::getParameter(fn (bool $p) => null);
        $mixedParam = self::getParameter(fn (mixed $p) => null);
        $nullableStringParam = self::getParameter(fn (?string $p) => null);
        $unionParam = self::getParameter(fn (string|int $p) => null);

        yield 'string param accepts string' => [$stringParam, 'test', true];
        yield 'string param accepts numeric string' => [$stringParam, '123', true];
        yield 'string param accepts int' => [$stringParam, 123, true];
        yield 'string param accepts float' => [$stringParam, 123.45, true];
        yield 'string param accepts bool' => [$stringParam, true, true];
        yield 'string param rejects null' => [$stringParam, null, false];
        yield 'string param rejects array' => [$stringParam, [], false];
        yield 'string param rejects object' => [$stringParam, new \stdClass(), false];

        yield 'int param accepts int' => [$intParam, 123, true];
        yield 'int param accepts numeric string' => [$intParam, '123', true];
        yield 'int param rejects non-numeric string' => [$intParam, 'abc', false];
        yield 'int param rejects float' => [$intParam, 123.45, false];
        yield 'int param rejects null' => [$intParam, null, false];

        yield 'float param accepts float' => [$floatParam, 123.45, true];
        yield 'float param accepts int' => [$floatParam, 123, true];
        yield 'float param accepts numeric string' => [$floatParam, '123.45', true];
        yield 'float param rejects non-numeric string' => [$floatParam, 'abc', false];

        yield 'bool param accepts bool true' => [$boolParam, true, true];
        yield 'bool param accepts bool false' => [$boolParam, false, true];
        yield 'bool param accepts string true' => [$boolParam, 'true', true];
        yield 'bool param accepts string false' => [$boolParam, 'false', true];
        yield 'bool param accepts string 1' => [$boolParam, '1', true];
        yield 'bool param accepts string 0' => [$boolParam, '0', true];
        yield 'bool param accepts int 1' => [$boolParam, 1, true];
        yield 'bool param accepts int 0' => [$boolParam, 0, true];
        yield 'bool param rejects other string' => [$boolParam, 'yes', false];

        yield 'mixed param accepts string' => [$mixedParam, 'test', true];
        yield 'mixed param accepts int' => [$mixedParam, 123, true];
        yield 'mixed param accepts null' => [$mixedParam, null, true];
        yield 'mixed param accepts array' => [$mixedParam, [], true];
        yield 'mixed param accepts object' => [$mixedParam, new \stdClass(), true];

        yield 'nullable string accepts string' => [$nullableStringParam, 'test', true];
        yield 'nullable string accepts null' => [$nullableStringParam, null, true];

        yield 'union param accepts string' => [$unionParam, 'test', true];
        yield 'union param accepts int' => [$unionParam, 123, true];
        yield 'union param rejects float' => [$unionParam, 123.45, false];
        yield 'union param rejects null' => [$unionParam, null, false];
    }

    private static function getParameter(callable $fn): \ReflectionParameter
    {
        $reflection = new \ReflectionFunction($fn);
        $parameters = $reflection->getParameters();

        return $parameters[0];
    }
}
