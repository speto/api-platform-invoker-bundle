<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Tests\Fixtures\Providers;

use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\Resources\UserResource;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects\CompanyId;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects\StringUserId;
use Speto\ApiPlatformInvokerBundle\UriVar\Attribute\MapUriVar;
use Symfony\Component\HttpFoundation\Request;

final readonly class TestInvokableProvider
{
    public function __invoke(
        #[MapUriVar('id')]
        StringUserId $userId,
        #[MapUriVar('companyId')]
        CompanyId $companyId,
        Request $request
    ): UserResource {
        $resource = new UserResource();
        $resource->id = $userId->value;
        $resource->companyId = $companyId->value;
        $resource->loaded = true;

        return $resource;
    }
}
