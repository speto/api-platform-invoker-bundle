<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Processor;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * Resolves the API Platform data parameter for invokable processors.
 *
 * This resolver handles injection of the $data parameter which contains
 * the input DTO/Resource from API Platform into invokable processors.
 */
final class DataValueResolver implements ValueResolverInterface
{
    /**
     * @return iterable<mixed>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $data = $request->attributes->get('_api_data');

        if ($data === null) {
            return [];
        }

        if (in_array($argument->getName(), ['data', 'input'], true)) {
            yield $data;
            return;
        }

        $type = $argument->getType();
        if ($type && is_object($data)) {
            if (class_exists($type) || interface_exists($type)) {
                if ($data instanceof $type) {
                    yield $data;
                    return;
                }
            }
        }

        return [];
    }
}
