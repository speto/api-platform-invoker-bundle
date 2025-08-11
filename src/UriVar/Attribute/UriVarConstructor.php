<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\UriVar\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class UriVarConstructor
{
    public function __construct(
        public string $method
    ) {
    }
}
