<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Tests\Fixtures\Processors;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\Resources\UserResource;

/**
 * @implements ProcessorInterface<UserResource, UserResource>
 */
final class TestTraditionalProcessor implements ProcessorInterface
{
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): UserResource {
        if (! $data instanceof UserResource) {
            throw new \InvalidArgumentException('Expected UserResource');
        }

        $data->processed = true;

        return $data;
    }
}
