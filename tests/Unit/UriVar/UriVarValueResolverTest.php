<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Tests\Unit\UriVar;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects\CompanyId;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects\SingleFactoryValueObject;
use Speto\ApiPlatformInvokerBundle\UriVar\Attribute\MapUriVar;
use Speto\ApiPlatformInvokerBundle\UriVar\UriVarInstantiator;
use Speto\ApiPlatformInvokerBundle\UriVar\UriVarValueResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class UriVarValueResolverTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private UriVarValueResolver $resolver;

    private UriVarInstantiator $instantiator;

    protected function setUp(): void
    {
        $this->instantiator = new UriVarInstantiator();
        $this->resolver = new UriVarValueResolver($this->instantiator);
    }

    public function testResolveWithMapUriVarAttribute(): void
    {
        $request = new Request();
        $request->attributes->set('companyId', 'company-123');

        $mapUriVar = new MapUriVar('companyId');

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getAttributes')
            ->with(MapUriVar::class, ArgumentMetadata::IS_INSTANCEOF)
            ->andReturn([$mapUriVar]);
        $argument->shouldReceive('getType')
            ->andReturn(SingleFactoryValueObject::class);

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        self::assertCount(1, $result);
        self::assertInstanceOf(SingleFactoryValueObject::class, $result[0]);
        self::assertSame('company-123', $result[0]->value);
    }

    public function testResolveWithoutMapUriVarAttribute(): void
    {
        $request = new Request();

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getAttributes')
            ->with(MapUriVar::class, ArgumentMetadata::IS_INSTANCEOF)
            ->andReturn([]);
        $argument->shouldReceive('getName')
            ->andReturn('nonExistentParam');

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        self::assertCount(0, $result);
    }

    public function testResolveWithMissingRequestAttribute(): void
    {
        $request = new Request();

        $mapUriVar = new MapUriVar('companyId');

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getAttributes')
            ->with(MapUriVar::class, ArgumentMetadata::IS_INSTANCEOF)
            ->andReturn([$mapUriVar]);

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        self::assertCount(0, $result);
    }

    public function testResolveWithoutType(): void
    {
        $request = new Request();
        $request->attributes->set('companyId', 'company-123');

        $mapUriVar = new MapUriVar('companyId');

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getAttributes')
            ->with(MapUriVar::class, ArgumentMetadata::IS_INSTANCEOF)
            ->andReturn([$mapUriVar]);
        $argument->shouldReceive('getType')
            ->andReturn(null);

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        self::assertCount(0, $result);
    }

    public function testResolveWithDifferentAttributeName(): void
    {
        $request = new Request();
        $request->attributes->set('userId', '456');
        $request->attributes->set('companyId', 'company-789');

        $mapUriVar = new MapUriVar('userId');

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getAttributes')
            ->with(MapUriVar::class, ArgumentMetadata::IS_INSTANCEOF)
            ->andReturn([$mapUriVar]);
        $argument->shouldReceive('getType')
            ->andReturn(SingleFactoryValueObject::class);

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        self::assertCount(1, $result);
        self::assertInstanceOf(SingleFactoryValueObject::class, $result[0]);
        self::assertSame('456', $result[0]->value);
    }

    public function testResolveWithNullValue(): void
    {
        $request = new Request();
        $request->attributes->set('companyId', null);

        $mapUriVar = new MapUriVar('companyId');

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getAttributes')
            ->with(MapUriVar::class, ArgumentMetadata::IS_INSTANCEOF)
            ->andReturn([$mapUriVar]);
        $argument->shouldReceive('getType')
            ->andReturn(SingleFactoryValueObject::class);

        self::expectException(\LogicException::class);
        self::expectExceptionMessage(
            'No usable constructor/factory for Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects\SingleFactoryValueObject.'
        );

        iterator_to_array($this->resolver->resolve($request, $argument));
    }

    public function testResolveWithMultipleAttributes(): void
    {
        $request = new Request();
        $request->attributes->set('companyId', 'company-123');

        $mapUriVar1 = new MapUriVar('companyId');
        $mapUriVar2 = new MapUriVar('userId');

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getAttributes')
            ->with(MapUriVar::class, ArgumentMetadata::IS_INSTANCEOF)
            ->andReturn([$mapUriVar1, $mapUriVar2]);
        $argument->shouldReceive('getType')
            ->andReturn(SingleFactoryValueObject::class);

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        self::assertCount(1, $result);
        self::assertInstanceOf(SingleFactoryValueObject::class, $result[0]);
        self::assertSame('company-123', $result[0]->value);
    }

    public function testMagicMappingWithCustomObject(): void
    {
        $request = new Request();
        $request->attributes->set('companyId', 'company-456');

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getAttributes')
            ->with(MapUriVar::class, ArgumentMetadata::IS_INSTANCEOF)
            ->andReturn([]);
        $argument->shouldReceive('getName')
            ->andReturn('companyId');
        $argument->shouldReceive('getType')
            ->andReturn(CompanyId::class);

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        self::assertCount(1, $result);
        self::assertInstanceOf(CompanyId::class, $result[0]);
        self::assertSame('company-456', $result[0]->value);
    }

    public function testMagicMappingWithStringType(): void
    {
        $request = new Request();
        $request->attributes->set('departmentId', 'engineering');

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getAttributes')
            ->with(MapUriVar::class, ArgumentMetadata::IS_INSTANCEOF)
            ->andReturn([]);
        $argument->shouldReceive('getName')
            ->andReturn('departmentId');
        $argument->shouldReceive('getType')
            ->andReturn('string');

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        self::assertCount(1, $result);
        self::assertSame('engineering', $result[0]);
    }

    public function testMagicMappingWithIntType(): void
    {
        $request = new Request();
        $request->attributes->set('userId', '123');

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getAttributes')
            ->with(MapUriVar::class, ArgumentMetadata::IS_INSTANCEOF)
            ->andReturn([]);
        $argument->shouldReceive('getName')
            ->andReturn('userId');
        $argument->shouldReceive('getType')
            ->andReturn('int');

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        self::assertCount(1, $result);
        self::assertSame(123, $result[0]);
    }

    public function testMagicMappingWithMissingUriVariable(): void
    {
        $request = new Request();

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getAttributes')
            ->with(MapUriVar::class, ArgumentMetadata::IS_INSTANCEOF)
            ->andReturn([]);
        $argument->shouldReceive('getName')
            ->andReturn('companyId');

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        self::assertCount(0, $result);
    }

    public function testMagicMappingWithMismatchedParameterName(): void
    {
        $request = new Request();
        $request->attributes->set('companyId', 'company-789');

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getAttributes')
            ->with(MapUriVar::class, ArgumentMetadata::IS_INSTANCEOF)
            ->andReturn([]);
        $argument->shouldReceive('getName')
            ->andReturn('differentName');

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        self::assertCount(0, $result);
    }

    public function testExplicitAttributeTakesPrecedenceOverMagicMapping(): void
    {
        $request = new Request();
        $request->attributes->set('companyId', 'magic-value');
        $request->attributes->set('userId', 'explicit-value');

        $mapUriVar = new MapUriVar('userId');

        $argument = Mockery::mock(ArgumentMetadata::class);
        $argument->shouldReceive('getAttributes')
            ->with(MapUriVar::class, ArgumentMetadata::IS_INSTANCEOF)
            ->andReturn([$mapUriVar]);
        $argument->shouldReceive('getName')
            ->andReturn('companyId');
        $argument->shouldReceive('getType')
            ->andReturn('string');

        $result = iterator_to_array($this->resolver->resolve($request, $argument));

        self::assertCount(1, $result);
        self::assertSame('explicit-value', $result[0]);
    }
}
