<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Tests\Unit\Common;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Speto\ApiPlatformInvokerBundle\Common\OperationValueResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class OperationValueResolverTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private OperationValueResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new OperationValueResolver();
    }

    public function testResolveOperationParameterWhenOperationIsInRequestAttributes(): void
    {
        $operation = new Post();
        $request = new Request();
        $request->attributes->set('_api_operation', $operation);

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getType')
            ->andReturn(Operation::class);
        $argument->shouldReceive('isNullable')
            ->once()
            ->andReturn(false);

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        self::assertCount(1, $result);
        self::assertSame($operation, $result[0]);
    }

    public function testResolveSpecificOperationSubclass(): void
    {
        $getOperation = new Get();
        $request = new Request();
        $request->attributes->set('_api_operation', $getOperation);

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getType')
            ->andReturn(Get::class);
        $argument->shouldReceive('isNullable')
            ->once()
            ->andReturn(false);

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        self::assertCount(1, $result);
        self::assertSame($getOperation, $result[0]);
    }

    public function testResolvePostOperationSubclass(): void
    {
        $postOperation = new Post();
        $request = new Request();
        $request->attributes->set('_api_operation', $postOperation);

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getType')
            ->andReturn(Post::class);
        $argument->shouldReceive('isNullable')
            ->once()
            ->andReturn(false);

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        self::assertCount(1, $result);
        self::assertSame($postOperation, $result[0]);
    }

    public function testResolveWhenNoOperationInAttributes(): void
    {
        $request = new Request();

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getType')
            ->andReturn(Operation::class);
        $argument->shouldReceive('isNullable')
            ->once()
            ->andReturn(false);

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        self::assertCount(0, $result);
    }

    public function testResolveWithWrongParameterType(): void
    {
        $operation = new Post();
        $request = new Request();
        $request->attributes->set('_api_operation', $operation);

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getType')
            ->andReturn('string');

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        self::assertCount(0, $result);
    }

    public function testResolveWithNullParameterType(): void
    {
        $operation = new Post();
        $request = new Request();
        $request->attributes->set('_api_operation', $operation);

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getType')
            ->andReturn(null);

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        self::assertCount(0, $result);
    }

    public function testResolveWithNullableOperationParameter(): void
    {
        $operation = new Get();
        $request = new Request();
        $request->attributes->set('_api_operation', $operation);

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getType')
            ->andReturn(Operation::class);
        $argument->shouldReceive('isNullable')
            ->andReturn(true);

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        self::assertCount(1, $result);
        self::assertSame($operation, $result[0]);
    }

    public function testResolveWithNullableOperationParameterWhenNoOperation(): void
    {
        $request = new Request();

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getType')
            ->andReturn(Operation::class);
        $argument->shouldReceive('isNullable')
            ->andReturn(true);

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        self::assertCount(1, $result);
        self::assertNull($result[0]);
    }

    public function testResolveOnlyResolvesOperationTypes(): void
    {
        $request = new Request();
        $request->attributes->set('_api_operation', new Post());
        $request->attributes->set('some_other_data', 'test');

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getType')
            ->andReturn(\stdClass::class);

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        self::assertCount(0, $result);
    }

    public function testResolveWithOperationSubclassWhenDifferentSubclassInRequest(): void
    {
        $postOperation = new Post();
        $request = new Request();
        $request->attributes->set('_api_operation', $postOperation);

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getType')
            ->andReturn(Get::class);
        $argument->shouldReceive('isNullable')
            ->twice()
            ->andReturn(false);

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        self::assertCount(0, $result);
    }

    public function testResolveWithGenericOperationTypeWhenSpecificSubclassInRequest(): void
    {
        $postOperation = new Post();
        $request = new Request();
        $request->attributes->set('_api_operation', $postOperation);

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getType')
            ->andReturn(Operation::class);
        $argument->shouldReceive('isNullable')
            ->once()
            ->andReturn(false);

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        self::assertCount(1, $result);
        self::assertSame($postOperation, $result[0]);
    }

    public function testResolveWithNullOperationInAttributes(): void
    {
        $request = new Request();
        $request->attributes->set('_api_operation', null);

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getType')
            ->andReturn(Operation::class);
        $argument->shouldReceive('isNullable')
            ->once()
            ->andReturn(false);

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        self::assertCount(0, $result);
    }

    public function testResolveWithInvalidOperationObjectInAttributes(): void
    {
        $request = new Request();
        $request->attributes->set('_api_operation', new \stdClass());

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getType')
            ->andReturn(Operation::class);
        $argument->shouldReceive('isNullable')
            ->once()
            ->andReturn(false);

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        self::assertCount(0, $result);
    }

    public function testResolveWithCustomOperationSubclass(): void
    {
        $customOperation = new class() extends Operation {
            public function __construct()
            {
                parent::__construct();
            }
        };

        $request = new Request();
        $request->attributes->set('_api_operation', $customOperation);

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getType')
            ->andReturn(Operation::class);
        $argument->shouldReceive('isNullable')
            ->once()
            ->andReturn(false);

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        self::assertCount(1, $result);
        self::assertSame($customOperation, $result[0]);
    }

    public function testResolveDoesNotResolveNonClassTypes(): void
    {
        $operation = new Post();
        $request = new Request();
        $request->attributes->set('_api_operation', $operation);

        $builtInTypes = ['string', 'int', 'float', 'bool', 'array', 'callable', 'iterable', 'object'];

        foreach ($builtInTypes as $type) {
            $argument = Mockery::mock(ArgumentMetadata::class);
            $argument->shouldReceive('getType')
                ->andReturn($type);

            $result = iterator_to_array($this->resolver->resolve($request, $argument));

            self::assertCount(0, $result, "Should not resolve built-in type: {$type}");
        }
    }

    public function testResolveWithInterfaceType(): void
    {
        $operation = new Post();
        $request = new Request();
        $request->attributes->set('_api_operation', $operation);

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getType')
            ->andReturn(\JsonSerializable::class);

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        if ($operation instanceof \JsonSerializable) {
            self::assertCount(1, $result);
            self::assertSame($operation, $result[0]);
        } else {
            self::assertCount(0, $result);
        }
    }
}
