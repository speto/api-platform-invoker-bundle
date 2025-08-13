<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Tests\Unit\UriVar;

use PHPUnit\Framework\TestCase;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects\AmbiguousValueObject;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects\FactoryTestValueObject;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects\InvalidValueObject;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects\SingleFactoryValueObject;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects\UserId;
use Speto\ApiPlatformInvokerBundle\UriVar\UriVarInstantiator;

final class UriVarInstantiatorTest extends TestCase
{
    private UriVarInstantiator $instantiator;

    protected function setUp(): void
    {
        $this->instantiator = new UriVarInstantiator();
    }

    public function testInstantiateWithAmbiguousFactoriesThrowsException(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Ambiguous factories for ' . AmbiguousValueObject::class);

        $this->instantiator->instantiate(AmbiguousValueObject::class, 'test-value');
    }

    public function testInstantiateWithUriVarConstructorAttribute(): void
    {
        $result = $this->instantiator->instantiate(UserId::class, '789');

        self::assertInstanceOf(UserId::class, $result);
        self::assertSame(789, $result->value);
    }

    public function testInstantiateWithUriVarConstructorAttributeIntValue(): void
    {
        $result = $this->instantiator->instantiate(UserId::class, 456);

        self::assertInstanceOf(UserId::class, $result);
        self::assertSame(456, $result->value);
    }

    public function testThrowsExceptionForNoUsableConstructor(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No usable constructor/factory for ' . InvalidValueObject::class);

        $this->instantiator->instantiate(InvalidValueObject::class, 'test');
    }

    /**
     * @dataProvider typeCoercionProvider
     */
    public function testTypeCoercion(string $class, mixed $value, mixed $expectedValue): void
    {
        $result = $this->instantiator->instantiate($class, $value);

        self::assertInstanceOf($class, $result);

        if ($class === SingleFactoryValueObject::class) {
            self::assertSame((string) $expectedValue, $result->value);
        } elseif ($class === UserId::class) {
            self::assertSame((int) $expectedValue, $result->value);
        }
    }

    /**
     * @return iterable<string, array{0: class-string, 1: mixed, 2: mixed}>
     */
    public static function typeCoercionProvider(): iterable
    {
        yield 'SingleFactoryValueObject with string' => [SingleFactoryValueObject::class, 'abc', 'abc'];
        yield 'SingleFactoryValueObject with int' => [SingleFactoryValueObject::class, 123, '123'];
        yield 'SingleFactoryValueObject with float' => [SingleFactoryValueObject::class, 123.45, '123.45'];
        yield 'UserId with string' => [UserId::class, '456', 456];
        yield 'UserId with int' => [UserId::class, 789, 789];
    }

    public function testInstantiateWithPrivateConstructorAndPublicFactory(): void
    {
        $result = $this->instantiator->instantiate(UserId::class, 999);

        self::assertInstanceOf(UserId::class, $result);
        self::assertSame(999, $result->value);
    }

    public function testThrowsExceptionForAmbiguousValueObject(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Ambiguous factories for ' . AmbiguousValueObject::class);

        $this->instantiator->instantiate(AmbiguousValueObject::class, 'test');
    }

    public function testInstantiateWithSingleFactory(): void
    {
        $result = $this->instantiator->instantiate(SingleFactoryValueObject::class, 'test-value');

        self::assertInstanceOf(SingleFactoryValueObject::class, $result);
        self::assertSame('test-value', $result->value);
    }

    public function testFactoryMethodValidation(): void
    {
        $result = $this->instantiator->instantiate(FactoryTestValueObject::class, 'test');

        self::assertSame('test', $result->value);
    }
}
