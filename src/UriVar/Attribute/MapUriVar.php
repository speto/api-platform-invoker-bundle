<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\UriVar\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class MapUriVar
{
    public function __construct(
        public string $name
    ) {
    }
}
