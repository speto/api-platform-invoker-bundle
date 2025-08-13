<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Tests\Fixtures\Resources;

final class UserResource
{
    public string $name = '';

    public string $email = '';

    public ?string $companyId = null;

    public bool $processed = false;

    public ?string $id = null;

    public bool $loaded = false;

    public bool $hasRequest = false;
}
