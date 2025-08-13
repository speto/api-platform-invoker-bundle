<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Tests\Unit\Processor;

use ApiPlatform\Metadata\Post;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Speto\ApiPlatformInvokerBundle\Processor\ActionInvoker;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\Resources\UserResource;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;

final class ActionInvokerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private ActionInvoker $invoker;

    private ArgumentResolverInterface $argumentResolver;

    protected function setUp(): void
    {
        $this->argumentResolver = Mockery::mock(ArgumentResolverInterface::class);
        $this->invoker = new ActionInvoker($this->argumentResolver);
    }

    public function testInvokeWithValidRequest(): void
    {
        $data = new UserResource();
        $data->name = 'John Doe';

        $operation = new Post();
        $uriVars = [
            'companyId' => 'company-123',
            'userId' => '456',
        ];

        $request = new Request();
        $context = [
            'request' => $request,
        ];

        $callable = function (UserResource $resource) {
            $resource->processed = true;
            return $resource;
        };

        $this->argumentResolver->shouldReceive('getArguments')
            ->with($request, $callable)
            ->once()
            ->andReturn([$data]);

        $result = ($this->invoker)($callable, $data, $operation, $uriVars, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->processed);
        self::assertSame('John Doe', $result->name);

        self::assertSame('company-123', $request->attributes->get('companyId'));
        self::assertSame('456', $request->attributes->get('userId'));
        self::assertSame($operation, $request->attributes->get('_api_operation'));
    }

    public function testInvokeThrowsExceptionWithoutRequest(): void
    {
        $data = new UserResource();
        $operation = new Post();
        $uriVars = [];
        $context = [];

        $callable = fn () => new UserResource();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No Request in $context; invokable processors are HTTP-only');

        ($this->invoker)($callable, $data, $operation, $uriVars, $context);
    }

    public function testInvokeThrowsExceptionForNonObjectReturn(): void
    {
        $data = new UserResource();
        $operation = new Post();
        $uriVars = [];

        $request = new Request();
        $context = [
            'request' => $request,
        ];

        $callable = fn () => 'not an object';

        $this->argumentResolver->shouldReceive('getArguments')
            ->with($request, $callable)
            ->once()
            ->andReturn([]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Processor must return an object (DTO/Resource)');

        ($this->invoker)($callable, $data, $operation, $uriVars, $context);
    }

    public function testInvokePreservesExistingRequestAttributes(): void
    {
        $data = new UserResource();
        $operation = new Post();
        $uriVars = [
            'companyId' => 'company-123',
        ];

        $request = new Request();
        $request->attributes->set('companyId', 'existing-value');
        $request->attributes->set('_route_params', [
            'existing' => 'param',
        ]);
        $context = [
            'request' => $request,
        ];

        $callable = fn ($resource) => $resource;

        $this->argumentResolver->shouldReceive('getArguments')
            ->with($request, $callable)
            ->once()
            ->andReturn([$data]);

        ($this->invoker)($callable, $data, $operation, $uriVars, $context);

        self::assertSame('existing-value', $request->attributes->get('companyId'));

        $routeParams = $request->attributes->get('_route_params');
        self::assertSame('param', $routeParams['existing']);
        self::assertSame('company-123', $routeParams['companyId']);
    }

    public function testInvokeWithMultipleArguments(): void
    {
        $data = new UserResource();
        $operation = new Post();
        $uriVars = [
            'companyId' => 'company-123',
        ];

        $request = new Request();
        $context = [
            'request' => $request,
        ];

        $secondArg = new \stdClass();
        $thirdArg = 'additional';

        $callable = function (UserResource $resource, \stdClass $obj, string $str) {
            $resource->processed = true;
            $resource->name = $str;
            return $resource;
        };

        $this->argumentResolver->shouldReceive('getArguments')
            ->with($request, $callable)
            ->once()
            ->andReturn([$data, $secondArg, $thirdArg]);

        $result = ($this->invoker)($callable, $data, $operation, $uriVars, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->processed);
        self::assertSame('additional', $result->name);
    }

    public function testInvokeWithEmptyUriVariables(): void
    {
        $data = new UserResource();
        $operation = new Post();
        $uriVars = [];

        $request = new Request();
        $context = [
            'request' => $request,
        ];

        $callable = fn ($resource) => $resource;

        $this->argumentResolver->shouldReceive('getArguments')
            ->with($request, $callable)
            ->once()
            ->andReturn([$data]);

        $result = ($this->invoker)($callable, $data, $operation, $uriVars, $context);

        self::assertSame($data, $result);
        self::assertSame($operation, $request->attributes->get('_api_operation'));
        self::assertIsArray($request->attributes->get('_route_params'));
    }

    public function testInvokeWithCallableObject(): void
    {
        $data = new UserResource();
        $operation = new Post();
        $uriVars = [];

        $request = new Request();
        $context = [
            'request' => $request,
        ];

        $callableObject = new class() {
            public function __invoke(UserResource $resource): UserResource
            {
                $resource->processed = true;
                return $resource;
            }
        };

        $this->argumentResolver->shouldReceive('getArguments')
            ->with($request, $callableObject)
            ->once()
            ->andReturn([$data]);

        $result = ($this->invoker)($callableObject, $data, $operation, $uriVars, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->processed);
    }
}
