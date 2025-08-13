<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects;

final readonly class InvalidValueObject
{
    public function __construct(
        public string $value,
        public int $required
    ) {
    }

    public static function createWithMultipleParams(string $value, int $required): self
    {
        return new self($value, $required);
    }
}
