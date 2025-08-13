<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Tests\Unit\Processor;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Speto\ApiPlatformInvokerBundle\Processor\ActionInvoker;
use Speto\ApiPlatformInvokerBundle\Processor\InvokableProcessorDecorator;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\Processors\TestInvokableProcessor;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\Processors\TestTraditionalProcessor;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\Resources\UserResource;

final class InvokableProcessorDecoratorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private InvokableProcessorDecorator $decorator;

    private ProcessorInterface $innerProcessor;

    private ContainerInterface $container;

    private ActionInvoker $actionInvoker;

    private $argumentResolver;

    protected function setUp(): void
    {
        $this->innerProcessor = Mockery::mock(ProcessorInterface::class);
        $this->container = Mockery::mock(ContainerInterface::class);

        $this->argumentResolver = Mockery::mock(
            \Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface::class
        );

        $this->actionInvoker = new ActionInvoker($this->argumentResolver);

        $this->decorator = new InvokableProcessorDecorator(
            $this->innerProcessor,
            $this->container,
            $this->actionInvoker
        );
    }

    public function testProcessWithInvokableProcessor(): void
    {
        $data = new UserResource();
        $data->name = 'John Doe';
        $operation = new Post(processor: 'test_invokable_processor');
        $uriVariables = [
            'companyId' => 'company-123',
        ];
        $request = new \Symfony\Component\HttpFoundation\Request();
        $context = [
            'request' => $request,
        ];

        $invokableProcessor = new TestInvokableProcessor();

        $this->container->shouldReceive('has')
            ->with('test_invokable_processor')
            ->once()
            ->andReturn(true);

        $this->container->shouldReceive('get')
            ->with('test_invokable_processor')
            ->once()
            ->andReturn($invokableProcessor);

        $this->argumentResolver->shouldReceive('getArguments')
            ->once()
            ->andReturnUsing(function ($req, $callable) use ($data, $request) {
                return [
                    $data,
                    new \Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects\CompanyId('company-123'),
                    $request,
                ];
            });

        $result = $this->decorator->process($data, $operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->processed);
        self::assertSame('company-123', $result->companyId);
    }

    public function testProcessWithTraditionalProcessor(): void
    {
        $data = new UserResource();
        $operation = new Post(processor: 'test_traditional_processor');
        $uriVariables = [
            'companyId' => 'company-123',
        ];
        $context = [];

        $traditionalProcessor = new TestTraditionalProcessor();

        $this->container->shouldReceive('has')
            ->with('test_traditional_processor')
            ->once()
            ->andReturn(true);

        $this->container->shouldReceive('get')
            ->with('test_traditional_processor')
            ->once()
            ->andReturn($traditionalProcessor);

        $expectedResult = new UserResource();

        $this->innerProcessor->shouldReceive('process')
            ->with($data, $operation, $uriVariables, $context)
            ->once()
            ->andReturn($expectedResult);

        $result = $this->decorator->process($data, $operation, $uriVariables, $context);

        self::assertSame($expectedResult, $result);
    }

    public function testProcessWithNonExistentService(): void
    {
        $data = new UserResource();
        $operation = new Post(processor: 'non_existent_service');
        $uriVariables = [];
        $context = [];

        $this->container->shouldReceive('has')
            ->with('non_existent_service')
            ->once()
            ->andReturn(false);

        $expectedResult = new UserResource();

        $this->innerProcessor->shouldReceive('process')
            ->with($data, $operation, $uriVariables, $context)
            ->once()
            ->andReturn($expectedResult);

        $result = $this->decorator->process($data, $operation, $uriVariables, $context);

        self::assertSame($expectedResult, $result);
    }

    public function testProcessWithNullProcessor(): void
    {
        $data = new UserResource();
        $operation = new Post();
        $uriVariables = [];
        $context = [];

        $expectedResult = new UserResource();

        $this->innerProcessor->shouldReceive('process')
            ->with($data, $operation, $uriVariables, $context)
            ->once()
            ->andReturn($expectedResult);

        $result = $this->decorator->process($data, $operation, $uriVariables, $context);

        self::assertSame($expectedResult, $result);
    }

    public function testProcessWithNonStringProcessor(): void
    {
        $data = new UserResource();

        $operation = new class() extends \ApiPlatform\Metadata\Operation {
            protected $processor = ['array', 'processor'];

            public function getProcessor(): callable|string|null
            {
                return $this->processor;
            }
        };

        $uriVariables = [];
        $context = [];

        $expectedResult = new UserResource();

        $this->innerProcessor->shouldReceive('process')
            ->with($data, $operation, $uriVariables, $context)
            ->once()
            ->andReturn($expectedResult);

        $result = $this->decorator->process($data, $operation, $uriVariables, $context);

        self::assertSame($expectedResult, $result);
    }

    public function testProcessWithCallableArray(): void
    {
        $data = new UserResource();
        $operation = new Post(processor: 'callable_array_service');
        $uriVariables = [
            'companyId' => 'test-123',
        ];
        $request = new \Symfony\Component\HttpFoundation\Request();
        $context = [
            'request' => $request,
        ];

        $callableService = [new TestInvokableProcessor(), '__invoke'];

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
            ->andReturnUsing(function ($req, $callable) use ($data, $request) {
                return [
                    $data,
                    new \Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects\CompanyId('test-123'),
                    $request,
                ];
            });

        $result = $this->decorator->process($data, $operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->processed);
    }

    public function testProcessWithClosure(): void
    {
        $data = new UserResource();
        $operation = new Post(processor: 'closure_service');
        $uriVariables = [];
        $request = new \Symfony\Component\HttpFoundation\Request();
        $context = [
            'request' => $request,
        ];

        $closure = function (UserResource $resource) {
            $resource->processed = true;
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
            ->andReturnUsing(function ($req, $callable) use ($data) {
                return [$data];
            });

        $result = $this->decorator->process($data, $operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->processed);
    }

    public function testProcessWithNonCallableService(): void
    {
        $data = new UserResource();
        $operation = new Post(processor: 'non_callable_service');
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

        $this->innerProcessor->shouldReceive('process')
            ->with($data, $operation, $uriVariables, $context)
            ->once()
            ->andReturn($expectedResult);

        $result = $this->decorator->process($data, $operation, $uriVariables, $context);

        self::assertSame($expectedResult, $result);
    }
}
