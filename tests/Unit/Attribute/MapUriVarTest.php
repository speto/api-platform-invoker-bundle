<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Tests\Unit\Attribute;

use PHPUnit\Framework\TestCase;
use Speto\ApiPlatformInvokerBundle\UriVar\Attribute\MapUriVar;

final class MapUriVarTest extends TestCase
{
    public function testAttributeConstruction(): void
    {
        $attribute = new MapUriVar('companyId');

        self::assertSame('companyId', $attribute->name);
    }

    public function testAttributeWithDifferentNames(): void
    {
        $testCases = ['userId', 'company_id', 'productSlug', 'order-number', '123'];

        foreach ($testCases as $name) {
            $attribute = new MapUriVar($name);
            self::assertSame($name, $attribute->name);
        }
    }

    public function testAttributeCanBeAppliedToParameter(): void
    {
        $reflection = new \ReflectionClass(MapUriVar::class);
        $attributes = $reflection->getAttributes();

        self::assertCount(1, $attributes);

        $attributeReflection = $attributes[0];
        self::assertSame(\Attribute::class, $attributeReflection->getName());

        $arguments = $attributeReflection->getArguments();
        self::assertSame(\Attribute::TARGET_PARAMETER, $arguments[0]);
    }

    public function testAttributeUsageInMethod(): void
    {
        $class = new class() {
            public function process(
                #[MapUriVar('companyId')]
                string $company,
                #[MapUriVar('userId')]
                int $user
            ): void {
            }
        };

        $reflection = new \ReflectionMethod($class, 'process');
        $parameters = $reflection->getParameters();

        $companyParam = $parameters[0];
        $companyAttributes = $companyParam->getAttributes(MapUriVar::class);
        self::assertCount(1, $companyAttributes);

        $companyAttribute = $companyAttributes[0]->newInstance();
        self::assertInstanceOf(MapUriVar::class, $companyAttribute);
        self::assertSame('companyId', $companyAttribute->name);

        $userParam = $parameters[1];
        $userAttributes = $userParam->getAttributes(MapUriVar::class);
        self::assertCount(1, $userAttributes);

        $userAttribute = $userAttributes[0]->newInstance();
        self::assertInstanceOf(MapUriVar::class, $userAttribute);
        self::assertSame('userId', $userAttribute->name);
    }
}
