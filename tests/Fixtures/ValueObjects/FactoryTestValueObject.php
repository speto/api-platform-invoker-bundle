<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects;

final readonly class FactoryTestValueObject
{
    private function __construct(
        public string $value
    ) {
    }

    public static function nonMatchingReturn(string $value): string
    {
        return $value;
    }

    public static function tooManyParams(string $v1, string $v2): self
    {
        return new self($v1);
    }

    public static function validFactory(string $value): self
    {
        return new self($value);
    }
}
