<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @template T1
 * @template T2
 * @implements ProcessorInterface<T1,T2>
 */
final readonly class InvokableProcessorDecorator implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<T1,T2> $inner
     * @param ActionInvoker<T1,T2>      $invoker
     */
    public function __construct(
        private ProcessorInterface $inner,
        private ContainerInterface $container,
        private ActionInvoker $invoker,
    ) {
    }

    /**
     * @param T1                        $data
     * @param array<string,mixed>       $uriVariables
     * @param array<string, mixed>&array{request?: Request, previous_data?: mixed, resource_class?: string, original_data?: mixed} $context
     * @return T2
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $id = $operation->getProcessor();
        if (! is_string($id) || ! $this->container->has($id)) {
            return $this->inner->process($data, $operation, $uriVariables, $context);
        }

        $svc = $this->container->get($id);

        if (is_callable($svc) && ! ($svc instanceof ProcessorInterface)) {
            return ($this->invoker)($svc, $data, $operation, $uriVariables, $context);
        }

        return $this->inner->process($data, $operation, $uriVariables, $context);
    }
}
