<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects\CompanyId;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects\UserId;
use Speto\ApiPlatformInvokerBundle\UriVar\Attribute\MapUriVar;
use Speto\ApiPlatformInvokerBundle\UriVar\UriVarInstantiator;
use Speto\ApiPlatformInvokerBundle\UriVar\UriVarValueResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\DefaultValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestAttributeValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\VariadicValueResolver;

final class UriVariableMappingTest extends TestCase
{
    private ArgumentResolver $argumentResolver;

    protected function setUp(): void
    {
        $uriVarInstantiator = new UriVarInstantiator();
        $uriVarValueResolver = new UriVarValueResolver($uriVarInstantiator);

        $this->argumentResolver = new ArgumentResolver(null, [
            new RequestAttributeValueResolver(),
            new RequestValueResolver(),
            $uriVarValueResolver,
            new DefaultValueResolver(),
            new VariadicValueResolver(),
        ]);
    }

    public function testMapSingleUriVariable(): void
    {
        $request = new Request();
        $request->attributes->set('companyId', 'acme-corp');

        $callable = function (#[MapUriVar('companyId')] CompanyId $company): string {
            return $company->value;
        };

        $arguments = $this->argumentResolver->getArguments($request, $callable);

        self::assertCount(1, $arguments);
        self::assertInstanceOf(CompanyId::class, $arguments[0]);
        self::assertSame('acme-corp', $arguments[0]->value);
    }

    public function testMapMultipleUriVariables(): void
    {
        $request = new Request();
        $request->attributes->set('companyId', 'tech-inc');
        $request->attributes->set('userId', '42');

        $callable = function (
            #[MapUriVar('companyId')]
            CompanyId $company,
            #[MapUriVar('userId')]
            UserId $user
        ): array {
            return [$company->value, $user->value];
        };

        $arguments = $this->argumentResolver->getArguments($request, $callable);

        self::assertCount(2, $arguments);
        self::assertInstanceOf(CompanyId::class, $arguments[0]);
        self::assertSame('tech-inc', $arguments[0]->value);
        self::assertInstanceOf(UserId::class, $arguments[1]);
        self::assertSame(42, $arguments[1]->value);
    }

    public function testMixedArgumentTypes(): void
    {
        $request = new Request();
        $request->attributes->set('companyId', 'mixed-corp');
        $request->attributes->set('departmentId', 'sales');

        $callable = function (
            Request $request,
            #[MapUriVar('companyId')]
            CompanyId $company,
            #[MapUriVar('departmentId')]
            string $department
        ): array {
            return [$request, $company, $department];
        };

        $arguments = $this->argumentResolver->getArguments($request, $callable);

        self::assertCount(3, $arguments);
        self::assertSame($request, $arguments[0]);
        self::assertInstanceOf(CompanyId::class, $arguments[1]);
        self::assertSame('mixed-corp', $arguments[1]->value);
        self::assertSame('sales', $arguments[2]);
    }

    public function testTypeCoercionInMapping(): void
    {
        $request = new Request();
        $request->attributes->set('userId', '999');

        $callable = function (#[MapUriVar('userId')] UserId $user): int {
            return $user->value;
        };

        $arguments = $this->argumentResolver->getArguments($request, $callable);

        self::assertCount(1, $arguments);
        self::assertInstanceOf(UserId::class, $arguments[0]);
        self::assertSame(999, $arguments[0]->value);
    }

    public function testOptionalUriVariable(): void
    {
        $request = new Request();

        $callable = function (#[MapUriVar('missingId')] ?CompanyId $company = null): ?CompanyId {
            return $company;
        };

        $arguments = $this->argumentResolver->getArguments($request, $callable);

        self::assertCount(1, $arguments);
        self::assertNull($arguments[0]);
    }

    public function testUriVariableWithStaticFactory(): void
    {
        $request = new Request();
        $request->attributes->set('companyId', 'factory-test');

        $callable = function (#[MapUriVar('companyId')] CompanyId $company): string {
            return $company->toString();
        };

        $arguments = $this->argumentResolver->getArguments($request, $callable);

        self::assertCount(1, $arguments);
        self::assertInstanceOf(CompanyId::class, $arguments[0]);
        self::assertSame('factory-test', $arguments[0]->toString());
    }

    public function testUriVariableWithCustomConstructor(): void
    {
        $request = new Request();
        $request->attributes->set('userId', 123);

        $callable = function (#[MapUriVar('userId')] UserId $user): int {
            return $user->toInt();
        };

        $arguments = $this->argumentResolver->getArguments($request, $callable);

        self::assertCount(1, $arguments);
        self::assertInstanceOf(UserId::class, $arguments[0]);
        self::assertSame(123, $arguments[0]->toInt());
    }

    public function testComplexUriPattern(): void
    {
        $request = new Request();
        $request->attributes->set('companyId', 'acme');
        $request->attributes->set('deptId', 'engineering');
        $request->attributes->set('userId', '789');

        $callable = function (
            #[MapUriVar('companyId')]
            CompanyId $company,
            #[MapUriVar('deptId')]
            string $department,
            #[MapUriVar('userId')]
            UserId $user
        ): string {
            return sprintf('%s/%s/%d', $company->value, $department, $user->value);
        };

        $arguments = $this->argumentResolver->getArguments($request, $callable);

        self::assertCount(3, $arguments);
        self::assertSame('acme', $arguments[0]->value);
        self::assertSame('engineering', $arguments[1]);
        self::assertSame(789, $arguments[2]->value);

        $result = $callable(...$arguments);
        self::assertSame('acme/engineering/789', $result);
    }
}
