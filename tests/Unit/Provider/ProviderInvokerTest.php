<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Tests\Unit\Provider;

use ApiPlatform\Metadata\Get;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Speto\ApiPlatformInvokerBundle\Provider\ProviderInvoker;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\Resources\UserResource;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects\CompanyId;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects\StringUserId;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;

final class ProviderInvokerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private ProviderInvoker $invoker;

    private ArgumentResolverInterface $argumentResolver;

    protected function setUp(): void
    {
        $this->argumentResolver = Mockery::mock(ArgumentResolverInterface::class);
        $this->invoker = new ProviderInvoker($this->argumentResolver);
    }

    public function testInvokeWithSimpleCallable(): void
    {
        $operation = new Get();
        $uriVariables = [
            'id' => 'user-123',
        ];
        $request = new Request();
        $context = [
            'request' => $request,
        ];

        $callable = function (StringUserId $userId) {
            $resource = new UserResource();
            $resource->id = $userId->value;
            $resource->loaded = true;
            return $resource;
        };

        $expectedUserId = new StringUserId('user-123');

        $this->argumentResolver->shouldReceive('getArguments')
            ->once()
            ->with($request, $callable)
            ->andReturn([$expectedUserId]);

        $result = ($this->invoker)($callable, $operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertSame('user-123', $result->id);
        self::assertTrue($result->loaded);
    }

    public function testInvokeWithMultipleParameters(): void
    {
        $operation = new Get();
        $uriVariables = [
            'id' => 'user-456',
            'companyId' => 'company-789',
        ];
        $request = new Request();
        $context = [
            'request' => $request,
        ];

        $callable = function (StringUserId $userId, CompanyId $companyId, Request $req) {
            $resource = new UserResource();
            $resource->id = $userId->value;
            $resource->companyId = $companyId->value;
            $resource->loaded = true;
            $resource->hasRequest = ($req instanceof Request);
            return $resource;
        };

        $expectedUserId = new StringUserId('user-456');
        $expectedCompanyId = new CompanyId('company-789');

        $this->argumentResolver->shouldReceive('getArguments')
            ->once()
            ->with($request, $callable)
            ->andReturn([$expectedUserId, $expectedCompanyId, $request]);

        $result = ($this->invoker)($callable, $operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertSame('user-456', $result->id);
        self::assertSame('company-789', $result->companyId);
        self::assertTrue($result->loaded);
        self::assertTrue($result->hasRequest);
    }

    public function testInvokeWithoutRequest(): void
    {
        $operation = new Get();
        $uriVariables = [];
        $context = [];

        $callable = function () {
            return new UserResource();
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No Request in $context; invokable providers are HTTP-only');

        ($this->invoker)($callable, $operation, $uriVariables, $context);
    }

    public function testInvokeWithInvalidRequestInContext(): void
    {
        $operation = new Get();
        $uriVariables = [];
        $context = [
            'request' => 'not-a-request',
        ];

        $callable = function () {
            return new UserResource();
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No Request in $context; invokable providers are HTTP-only');

        ($this->invoker)($callable, $operation, $uriVariables, $context);
    }

    public function testInvokeEnsuresUriVariablesInRequestAttributes(): void
    {
        $operation = new Get();
        $uriVariables = [
            'id' => 'user-001',
            'companyId' => 'company-002',
        ];
        $request = new Request();
        $context = [
            'request' => $request,
        ];

        $request->attributes->set('id', 'should-not-be-overwritten');

        $callable = function () {
            return new UserResource();
        };

        $this->argumentResolver->shouldReceive('getArguments')
            ->once()
            ->andReturnUsing(function (Request $req, $callable) {
                self::assertSame('should-not-be-overwritten', $req->attributes->get('id'));
                self::assertSame('company-002', $req->attributes->get('companyId'));

                $routeParams = $req->attributes->get('_route_params');
                self::assertIsArray($routeParams);
                self::assertArrayHasKey('id', $routeParams);
                self::assertArrayHasKey('companyId', $routeParams);

                return [];
            });

        ($this->invoker)($callable, $operation, $uriVariables, $context);
    }

    public function testInvokeStoresOperationInRequestAttributes(): void
    {
        $operation = new Get();
        $uriVariables = [];
        $request = new Request();
        $context = [
            'request' => $request,
        ];

        $callable = function () {
            return new UserResource();
        };

        $this->argumentResolver->shouldReceive('getArguments')
            ->once()
            ->andReturnUsing(function (Request $req, $callable) use ($operation) {
                self::assertSame($operation, $req->attributes->get('_api_operation'));
                return [];
            });

        ($this->invoker)($callable, $operation, $uriVariables, $context);
    }

    public function testInvokeWithNonObjectReturn(): void
    {
        $operation = new Get();
        $uriVariables = [];
        $request = new Request();
        $context = [
            'request' => $request,
        ];

        $callable = function () {
            return 'not-an-object';
        };

        $this->argumentResolver->shouldReceive('getArguments')
            ->once()
            ->andReturn([]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Provider must return an object or iterable');

        ($this->invoker)($callable, $operation, $uriVariables, $context);
    }

    public function testInvokeWithArrayReturn(): void
    {
        $operation = new \ApiPlatform\Metadata\GetCollection();
        $uriVariables = [];
        $request = new Request();
        $context = [
            'request' => $request,
        ];

        $callable = function () {
            return [$this->createUserResource('user-1'), $this->createUserResource('user-2')];
        };

        $this->argumentResolver->shouldReceive('getArguments')
            ->once()
            ->andReturn([]);

        $result = ($this->invoker)($callable, $operation, $uriVariables, $context);

        self::assertIsArray($result);
        self::assertCount(2, $result);
        self::assertInstanceOf(UserResource::class, $result[0]);
        self::assertInstanceOf(UserResource::class, $result[1]);
    }

    public function testInvokeWithIterableReturn(): void
    {
        $operation = new \ApiPlatform\Metadata\GetCollection();
        $uriVariables = [];
        $request = new Request();
        $context = [
            'request' => $request,
        ];

        $callable = function () {
            yield $this->createUserResource('user-1');
            yield $this->createUserResource('user-2');
            yield $this->createUserResource('user-3');
        };

        $this->argumentResolver->shouldReceive('getArguments')
            ->once()
            ->andReturn([]);

        $result = ($this->invoker)($callable, $operation, $uriVariables, $context);

        self::assertIsIterable($result);

        $items = iterator_to_array($result);
        self::assertCount(3, $items);
        self::assertInstanceOf(UserResource::class, $items[0]);
    }

    public function testInvokeWithCallableArray(): void
    {
        $operation = new Get();
        $uriVariables = [
            'id' => 'user-999',
        ];
        $request = new Request();
        $context = [
            'request' => $request,
        ];

        $object = new class() {
            public function provide(StringUserId $userId): UserResource
            {
                $resource = new UserResource();
                $resource->id = $userId->value;
                $resource->loaded = true;
                return $resource;
            }
        };

        $callable = [$object, 'provide'];
        $expectedUserId = new StringUserId('user-999');

        $this->argumentResolver->shouldReceive('getArguments')
            ->once()
            ->with($request, $callable)
            ->andReturn([$expectedUserId]);

        $result = ($this->invoker)($callable, $operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertSame('user-999', $result->id);
        self::assertTrue($result->loaded);
    }

    private function createUserResource(string $id): UserResource
    {
        $resource = new UserResource();
        $resource->id = $id;
        $resource->loaded = true;
        return $resource;
    }
}
