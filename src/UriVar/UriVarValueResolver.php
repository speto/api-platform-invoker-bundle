<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\UriVar;

use Speto\ApiPlatformInvokerBundle\UriVar\Attribute\MapUriVar;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class UriVarValueResolver implements ValueResolverInterface
{
    public function __construct(
        private UriVarInstantiator $instantiator
    ) {
    }

    /**
     * @return iterable<mixed>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $attrs = $argument->getAttributes(MapUriVar::class, ArgumentMetadata::IS_INSTANCEOF);
        if (! $attrs) {
            return [];
        }

        /** @var MapUriVar $attr */
        $attr = $attrs[0];
        $name = $attr->name;

        if (! $request->attributes->has($name)) {
            return [];
        }

        $type = $argument->getType();
        if (! $type) {
            return [];
        } // need class type

        /** @var class-string $type */
        yield $this->instantiator->instantiate($type, $request->attributes->get($name));
    }
}
