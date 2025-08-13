<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Provider;

use ApiPlatform\Metadata\Operation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;

/**
 * Invokes a callable provider with the current request and operation context.
 *
 * This class is used to invoke providers that are defined as callable services
 * in the API Platform configuration. It resolves the arguments from the current
 * request and operation, allowing for dynamic data retrieval.
 */
final readonly class ProviderInvoker
{
    public function __construct(
        private ArgumentResolverInterface $argumentResolver,
    ) {
    }

    /**
     * @param array<string,mixed> $uriVars
     * @param array<string, mixed>&array{request?: Request, filters?: array<string,mixed>} $context
     * @return object|array<array-key,mixed>|null
     */
    public function __invoke(callable $callable, Operation $op, array $uriVars, array $context): object|array|null
    {
        $request = ($context['request'] ?? null);
        if (! $request instanceof Request) {
            throw new \LogicException(
                'No Request in $context; invokable providers are HTTP-only. Ensure API Platform passes the Request.'
            );
        }

        $request->attributes->set('_api_operation', $op);

        foreach ($uriVars as $k => $v) {
            if (! $request->attributes->has($k)) {
                $request->attributes->set($k, $v);
            }
        }
        $request->attributes->set('_route_params', $uriVars + (array) $request->attributes->get('_route_params', []));

        $args = $this->argumentResolver->getArguments($request, $callable);
        $result = $callable(...$args);

        if ($result !== null && ! is_object($result) && ! is_array($result)) {
            throw new \LogicException('Provider must return an object or iterable (or null).');
        }
        return $result;
    }
}
