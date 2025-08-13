<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects;

use Speto\ApiPlatformInvokerBundle\UriVar\Attribute\UriVarConstructor;

#[UriVarConstructor('create')]
final readonly class StringUserId
{
    public function __construct(
        public string $value
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function create(string $value): self
    {
        return new self($value);
    }
}
