<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Tests\Fixtures\Processors;

use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\Resources\UserResource;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects\CompanyId;
use Speto\ApiPlatformInvokerBundle\UriVar\Attribute\MapUriVar;
use Symfony\Component\HttpFoundation\Request;

final readonly class TestInvokableProcessor
{
    public function __invoke(
        UserResource $data,
        #[MapUriVar('companyId')]
        CompanyId $companyId,
        Request $request
    ): UserResource {
        $data->companyId = $companyId->value;
        $data->processed = true;

        return $data;
    }
}
