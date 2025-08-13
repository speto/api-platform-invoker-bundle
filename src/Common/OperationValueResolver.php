<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Common;

use ApiPlatform\Metadata\Operation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * Resolves API Platform Operation parameters for invokable processors and providers.
 *
 * This resolver handles injection of the Operation parameter which contains
 * the current API Platform operation metadata into invokable processors and providers.
 */
final class OperationValueResolver implements ValueResolverInterface
{
    /**
     * @return iterable<Operation|null>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $type = $argument->getType();

        if (! $type) {
            return [];
        }

        if (! class_exists($type) && ! interface_exists($type)) {
            return [];
        }

        if ($type !== Operation::class && ! is_subclass_of($type, Operation::class)) {
            return [];
        }

        $operation = $request->attributes->get('_api_operation');

        if ($argument->isNullable() && $operation === null) {
            yield null;
            return;
        }

        if (! $operation instanceof Operation) {
            return [];
        }

        if ($type === Operation::class || $operation instanceof $type) {
            yield $operation;
            return;
        }

        if ($argument->isNullable()) {
            yield null;
            return;
        }

        return [];
    }
}
