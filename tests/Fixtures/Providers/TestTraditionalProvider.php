<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Tests\Fixtures\Providers;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\Resources\UserResource;

final class TestTraditionalProvider implements ProviderInterface
{
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $resource = new UserResource();
        $resource->id = $uriVariables['id'] ?? 'default-id';
        $resource->loaded = true;

        return $resource;
    }
}
