<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Decorates the API Platform state provider to handle invokable providers.
 * @implements ProviderInterface<object>
 */
final readonly class InvokableProviderDecorator implements ProviderInterface
{
    /**
     * @param ProviderInterface<object> $inner
     */
    public function __construct(
        private ProviderInterface $inner,
        private ContainerInterface $container,
        private ProviderInvoker $invoker,
    ) {
    }

    /**
     * @param array<string,mixed>       $uriVariables
     * @param array<string, mixed>&array{request?: Request, filters?: array<string,mixed>} $context
     * @return object|array<array-key,mixed>|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $id = $operation->getProvider();
        if (! is_string($id) || ! $this->container->has($id)) {
            return $this->inner->provide($operation, $uriVariables, $context);
        }

        $svc = $this->container->get($id);

        if (is_callable($svc) && ! ($svc instanceof ProviderInterface)) {
            return ($this->invoker)($svc, $operation, $uriVariables, $context);
        }

        return $this->inner->provide($operation, $uriVariables, $context);
    }
}
