<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Processor;

use ApiPlatform\Metadata\Operation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;

/**
 * Invokes a callable processor with the current request and operation context.
 *
 * This class is used to invoke processors that are defined as callable services
 * in the API Platform configuration. It resolves the arguments from the current
 * request and operation, allowing for dynamic processing of data.
 *
 * @template T1
 * @template T2
 */
final readonly class ActionInvoker
{
    public function __construct(
        private ArgumentResolverInterface $argumentResolver,
    ) {
    }

    /**
     * @param T1 $data
     * @param array<string,mixed> $uriVars
     * @param array<string, mixed>&array{request?: Request, previous_data?: mixed, resource_class?: string, original_data?: mixed} $context
     *
     * @return T2
     */
    public function __invoke(callable $callable, mixed $data, Operation $op, array $uriVars, array $context)
    {
        $request = ($context['request'] ?? null);
        if (! $request instanceof Request) {
            throw new \LogicException(
                'No Request in $context; invokable processors are HTTP-only. Ensure API Platform passes the Request.'
            );
        }

        // Ensure attributes resolvers expect are present (e.g., MapEntity)
        foreach ($uriVars as $k => $v) {
            if (! $request->attributes->has($k)) {
                $request->attributes->set($k, $v);
            }
        }
        $request->attributes->set('_route_params', $uriVars + (array) $request->attributes->get('_route_params', []));
        $request->attributes->set('_api_operation', $op);

        $args = $this->argumentResolver->getArguments($request, $callable);
        /** @var T2 $result */
        $result = $callable(...$args);

        if (! is_object($result)) {
            throw new \LogicException('Processor must return an object (DTO/Resource).');
        }
        return $result;
    }
}
