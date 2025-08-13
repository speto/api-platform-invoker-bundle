<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Tests\Unit\Attribute;

use PHPUnit\Framework\TestCase;
use Speto\ApiPlatformInvokerBundle\UriVar\Attribute\UriVarConstructor;

final class UriVarConstructorTest extends TestCase
{
    public function testAttributeConstruction(): void
    {
        $attribute = new UriVarConstructor('fromString');

        self::assertSame('fromString', $attribute->method);
    }

    public function testAttributeWithDifferentMethodNames(): void
    {
        $testCases = ['create', 'fromInt', 'fromArray', 'buildFromString', 'factory'];

        foreach ($testCases as $methodName) {
            $attribute = new UriVarConstructor($methodName);
            self::assertSame($methodName, $attribute->method);
        }
    }

    public function testAttributeCanBeAppliedToClass(): void
    {
        $reflection = new \ReflectionClass(UriVarConstructor::class);
        $attributes = $reflection->getAttributes();

        self::assertCount(1, $attributes);

        $attributeReflection = $attributes[0];
        self::assertSame(\Attribute::class, $attributeReflection->getName());

        $arguments = $attributeReflection->getArguments();
        self::assertSame(\Attribute::TARGET_CLASS, $arguments[0]);
    }

    public function testAttributeUsageOnClass(): void
    {
        $testClass = new #[UriVarConstructor('create')] class('dummy') {
            public function __construct(
                public string $value
            ) {
            }

            public static function create(string $value): self
            {
                return new self($value);
            }
        };

        $reflection = new \ReflectionClass($testClass);
        $attributes = $reflection->getAttributes(UriVarConstructor::class);

        self::assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();
        self::assertInstanceOf(UriVarConstructor::class, $attribute);
        self::assertSame('create', $attribute->method);
    }

    public function testMultipleConstructorMethods(): void
    {
        $valueObject = new #[UriVarConstructor('fromString')] class('dummy') {
            public function __construct(
                public string $value
            ) {
            }

            public static function fromString(string $value): self
            {
                return new self($value);
            }

            public static function fromInt(int $value): self
            {
                return new self((string) $value);
            }
        };

        $reflection = new \ReflectionClass($valueObject);
        $attributes = $reflection->getAttributes(UriVarConstructor::class);

        self::assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();
        self::assertSame('fromString', $attribute->method);
    }
}
