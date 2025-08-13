<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Tests\Unit\Provider;

use ApiPlatform\Metadata\Get;
use ApiPlatform\State\ProviderInterface;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Speto\ApiPlatformInvokerBundle\Provider\InvokableProviderDecorator;
use Speto\ApiPlatformInvokerBundle\Provider\ProviderInvoker;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\Providers\TestInvokableProvider;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\Providers\TestTraditionalProvider;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\Resources\UserResource;

final class InvokableProviderDecoratorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private InvokableProviderDecorator $decorator;

    private ProviderInterface $innerProvider;

    private ContainerInterface $container;

    private ProviderInvoker $providerInvoker;

    private $argumentResolver;

    protected function setUp(): void
    {
        $this->innerProvider = Mockery::mock(ProviderInterface::class);
        $this->container = Mockery::mock(ContainerInterface::class);

        $this->argumentResolver = Mockery::mock(
            \Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface::class
        );

        $this->providerInvoker = new ProviderInvoker($this->argumentResolver);

        $this->decorator = new InvokableProviderDecorator(
            $this->innerProvider,
            $this->container,
            $this->providerInvoker
        );
    }

    public function testProvideWithInvokableProvider(): void
    {
        $operation = new Get(provider: 'test_invokable_provider');
        $uriVariables = [
            'id' => 'user-123',
            'companyId' => 'company-456',
        ];
        $request = new \Symfony\Component\HttpFoundation\Request();
        $context = [
            'request' => $request,
        ];

        $invokableProvider = new TestInvokableProvider();

        $this->container->shouldReceive('has')
            ->with('test_invokable_provider')
            ->once()
            ->andReturn(true);

        $this->container->shouldReceive('get')
            ->with('test_invokable_provider')
            ->once()
            ->andReturn($invokableProvider);

        $this->argumentResolver->shouldReceive('getArguments')
            ->once()
            ->andReturnUsing(function ($req, $callable) {
                return [
                    new \Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects\StringUserId('user-123'),
                    new \Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects\CompanyId('company-456'),
                    $req,
                ];
            });

        $result = $this->decorator->provide($operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertSame('user-123', $result->id);
        self::assertSame('company-456', $result->companyId);
        self::assertTrue($result->loaded);
    }

    public function testProvideWithTraditionalProvider(): void
    {
        $operation = new Get(provider: 'test_traditional_provider');
        $uriVariables = [
            'id' => 'user-123',
        ];
        $context = [];

        $traditionalProvider = new TestTraditionalProvider();

        $this->container->shouldReceive('has')
            ->with('test_traditional_provider')
            ->once()
            ->andReturn(true);

        $this->container->shouldReceive('get')
            ->with('test_traditional_provider')
            ->once()
            ->andReturn($traditionalProvider);

        $expectedResult = new UserResource();

        $this->innerProvider->shouldReceive('provide')
            ->with($operation, $uriVariables, $context)
            ->once()
            ->andReturn($expectedResult);

        $result = $this->decorator->provide($operation, $uriVariables, $context);

        self::assertSame($expectedResult, $result);
    }

    public function testProvideWithNonExistentService(): void
    {
        $operation = new Get(provider: 'non_existent_service');
        $uriVariables = [];
        $context = [];

        $this->container->shouldReceive('has')
            ->with('non_existent_service')
            ->once()
            ->andReturn(false);

        $expectedResult = new UserResource();

        $this->innerProvider->shouldReceive('provide')
            ->with($operation, $uriVariables, $context)
            ->once()
            ->andReturn($expectedResult);

        $result = $this->decorator->provide($operation, $uriVariables, $context);

        self::assertSame($expectedResult, $result);
    }

    public function testProvideWithNullProvider(): void
    {
        $operation = new Get();
        $uriVariables = [];
        $context = [];

        $expectedResult = new UserResource();

        $this->innerProvider->shouldReceive('provide')
            ->with($operation, $uriVariables, $context)
            ->once()
            ->andReturn($expectedResult);

        $result = $this->decorator->provide($operation, $uriVariables, $context);

        self::assertSame($expectedResult, $result);
    }

    public function testProvideWithNonStringProvider(): void
    {
        $operation = new class() extends \ApiPlatform\Metadata\Operation {
            protected $provider = ['array', 'provider'];

            public function getProvider(): callable|string|null
            {
                return $this->provider;
            }
        };

        $uriVariables = [];
        $context = [];

        $expectedResult = new UserResource();

        $this->innerProvider->shouldReceive('provide')
            ->with($operation, $uriVariables, $context)
            ->once()
            ->andReturn($expectedResult);

        $result = $this->decorator->provide($operation, $uriVariables, $context);

        self::assertSame($expectedResult, $result);
    }

    public function testProvideWithCallableArray(): void
    {
        $operation = new Get(provider: 'callable_array_service');
        $uriVariables = [
            'id' => 'test-123',
            'companyId' => 'test-456',
        ];
        $request = new \Symfony\Component\HttpFoundation\Request();
        $context = [
            'request' => $request,
        ];

        $callableService = [new TestInvokableProvider(), '__invoke'];

        $this->container->shouldReceive('has')
            ->with('callable_array_service')
            ->once()
            ->andReturn(true);

        $this->container->shouldReceive('get')
            ->with('callable_array_service')
            ->once()
            ->andReturn($callableService);

        $this->argumentResolver->shouldReceive('getArguments')
            ->once()
            ->andReturnUsing(function ($req, $callable) use ($request) {
                return [
                    new \Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects\StringUserId('test-123'),
                    new \Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects\CompanyId('test-456'),
                    $request,
                ];
            });

        $result = $this->decorator->provide($operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->loaded);
    }

    public function testProvideWithClosure(): void
    {
        $operation = new Get(provider: 'closure_service');
        $uriVariables = [];
        $request = new \Symfony\Component\HttpFoundation\Request();
        $context = [
            'request' => $request,
        ];

        $closure = function () {
            $resource = new UserResource();
            $resource->loaded = true;
            return $resource;
        };

        $this->container->shouldReceive('has')
            ->with('closure_service')
            ->once()
            ->andReturn(true);

        $this->container->shouldReceive('get')
            ->with('closure_service')
            ->once()
            ->andReturn($closure);

        $this->argumentResolver->shouldReceive('getArguments')
            ->once()
            ->andReturnUsing(function ($req, $callable) {
                return [];
            });

        $result = $this->decorator->provide($operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->loaded);
    }

    public function testProvideWithNonCallableService(): void
    {
        $operation = new Get(provider: 'non_callable_service');
        $uriVariables = [];
        $context = [];

        $nonCallableService = new \stdClass();

        $this->container->shouldReceive('has')
            ->with('non_callable_service')
            ->once()
            ->andReturn(true);

        $this->container->shouldReceive('get')
            ->with('non_callable_service')
            ->once()
            ->andReturn($nonCallableService);

        $expectedResult = new UserResource();

        $this->innerProvider->shouldReceive('provide')
            ->with($operation, $uriVariables, $context)
            ->once()
            ->andReturn($expectedResult);

        $result = $this->decorator->provide($operation, $uriVariables, $context);

        self::assertSame($expectedResult, $result);
    }

    public function testProvideCollection(): void
    {
        $operation = new \ApiPlatform\Metadata\GetCollection(provider: 'collection_provider');
        $uriVariables = [];
        $request = new \Symfony\Component\HttpFoundation\Request();
        $context = [
            'request' => $request,
        ];

        $closure = function () {
            return [
                $this->createUserResource('user-1'),
                $this->createUserResource('user-2'),
                $this->createUserResource('user-3'),
            ];
        };

        $this->container->shouldReceive('has')
            ->with('collection_provider')
            ->once()
            ->andReturn(true);

        $this->container->shouldReceive('get')
            ->with('collection_provider')
            ->once()
            ->andReturn($closure);

        $this->argumentResolver->shouldReceive('getArguments')
            ->once()
            ->andReturnUsing(function ($req, $callable) {
                return [];
            });

        $result = $this->decorator->provide($operation, $uriVariables, $context);

        self::assertIsArray($result);
        self::assertCount(3, $result);
        self::assertInstanceOf(UserResource::class, $result[0]);
    }

    private function createUserResource(string $id): UserResource
    {
        $resource = new UserResource();
        $resource->id = $id;
        $resource->loaded = true;
        return $resource;
    }
}
