<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects;

final readonly class AmbiguousValueObject
{
    public function __construct(
        public string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function create(string $value): self
    {
        return new self($value);
    }
}
