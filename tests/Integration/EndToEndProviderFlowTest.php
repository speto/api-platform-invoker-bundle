<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Tests\Integration;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\ProviderInterface;
use PHPUnit\Framework\TestCase;
use Speto\ApiPlatformInvokerBundle\Common\OperationValueResolver;
use Speto\ApiPlatformInvokerBundle\Provider\InvokableProviderDecorator;
use Speto\ApiPlatformInvokerBundle\Provider\ProviderInvoker;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\Providers\TestInvokableProvider;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\Resources\UserResource;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects\CompanyId;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects\StringUserId;
use Speto\ApiPlatformInvokerBundle\UriVar\Attribute\MapUriVar;
use Speto\ApiPlatformInvokerBundle\UriVar\UriVarInstantiator;
use Speto\ApiPlatformInvokerBundle\UriVar\UriVarValueResolver;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\DefaultValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestAttributeValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\VariadicValueResolver;

final class EndToEndProviderFlowTest extends TestCase
{
    private Container $container;

    private InvokableProviderDecorator $decorator;

    protected function setUp(): void
    {
        $this->container = new Container();

        $uriVarInstantiator = new UriVarInstantiator();
        $uriVarValueResolver = new UriVarValueResolver($uriVarInstantiator);

        $operationResolver = new OperationValueResolver();

        $argumentResolver = new ArgumentResolver(null, [
            $operationResolver,
            $uriVarValueResolver,
            new RequestAttributeValueResolver(),
            new RequestValueResolver(),
            new DefaultValueResolver(),
            new VariadicValueResolver(),
        ]);

        $providerInvoker = new ProviderInvoker($argumentResolver);

        $innerProvider = new class() implements ProviderInterface {
            public function provide(
                \ApiPlatform\Metadata\Operation $operation,
                array $uriVariables = [],
                array $context = []
            ): object|array|null {
                return null;
            }
        };

        $this->decorator = new InvokableProviderDecorator($innerProvider, $this->container, $providerInvoker);

        $this->container->set('test_provider', new TestInvokableProvider());
    }

    public function testCompleteFlowWithInvokableProvider(): void
    {
        $operation = new Get(provider: 'test_provider');

        $uriVariables = [
            'id' => 'user-123',
            'companyId' => 'company-456',
        ];

        $request = new Request();

        $context = [
            'request' => $request,
        ];

        $result = $this->decorator->provide($operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->loaded);
        self::assertSame('user-123', $result->id);
        self::assertSame('company-456', $result->companyId);
    }

    public function testCompleteFlowWithMultipleUriVariables(): void
    {
        $provider = new class() {
            public function __invoke(
                #[MapUriVar('id')]
                StringUserId $userId,
                #[MapUriVar('companyId')]
                CompanyId $companyId,
                #[MapUriVar('departmentId')]
                string $departmentId,
                Request $request
            ): UserResource {
                $resource = new UserResource();
                $resource->id = $userId->value;
                $resource->companyId = $companyId->value;
                $resource->email = $departmentId . '@' . $companyId->value . '.com';
                $resource->loaded = true;
                return $resource;
            }
        };

        $this->container->set('multi_var_provider', $provider);

        $operation = new Get(provider: 'multi_var_provider');
        $uriVariables = [
            'id' => 'user-789',
            'companyId' => 'acme-corp',
            'departmentId' => 'engineering',
        ];

        $request = new Request();
        $context = [
            'request' => $request,
        ];

        $result = $this->decorator->provide($operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->loaded);
        self::assertSame('user-789', $result->id);
        self::assertSame('acme-corp', $result->companyId);
        self::assertSame('engineering@acme-corp.com', $result->email);
    }

    public function testFallbackToInnerProviderWhenNotInvokable(): void
    {
        $traditionalProvider = new class() implements ProviderInterface {
            public function provide(
                \ApiPlatform\Metadata\Operation $operation,
                array $uriVariables = [],
                array $context = []
            ): object|array|null {
                $resource = new UserResource();
                $resource->loaded = true;
                $resource->email = 'traditional@example.com';
                return $resource;
            }
        };

        $this->container->set('traditional_provider', $traditionalProvider);

        $operation = new Get(provider: 'traditional_provider');
        $uriVariables = [];
        $context = [
            'request' => new Request(),
        ];

        $result = $this->decorator->provide($operation, $uriVariables, $context);

        self::assertNull($result);
    }

    public function testProviderWithoutUriVariables(): void
    {
        $provider = new class() {
            public function __invoke(Request $request): UserResource
            {
                $resource = new UserResource();
                $resource->loaded = true;
                $resource->email = 'no-uri-vars@example.com';
                return $resource;
            }
        };

        $this->container->set('no_uri_provider', $provider);

        $operation = new Get(provider: 'no_uri_provider');
        $uriVariables = [];
        $context = [
            'request' => new Request(),
        ];

        $result = $this->decorator->provide($operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->loaded);
        self::assertSame('no-uri-vars@example.com', $result->email);
    }

    public function testProviderWithServiceInjection(): void
    {
        $mockService = new class() {
            public function transform(string $value): string
            {
                return strtoupper($value);
            }
        };

        $provider = new class($mockService) {
            public function __construct(
                private $transformer
            ) {
            }

            public function __invoke(#[MapUriVar('companyId')] CompanyId $companyId): UserResource
            {
                $resource = new UserResource();
                $resource->companyId = $this->transformer->transform($companyId->value);
                $resource->loaded = true;
                return $resource;
            }
        };

        $this->container->set('service_injection_provider', $provider);

        $operation = new Get(provider: 'service_injection_provider');
        $uriVariables = [
            'companyId' => 'lowercase-company',
        ];
        $context = [
            'request' => new Request(),
        ];

        $result = $this->decorator->provide($operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->loaded);
        self::assertSame('LOWERCASE-COMPANY', $result->companyId);
    }

    public function testProviderReturningCollection(): void
    {
        $provider = new class() {
            public function __invoke(#[MapUriVar('companyId')] CompanyId $companyId): array
            {
                $users = [];
                for ($i = 1; $i <= 3; $i++) {
                    $resource = new UserResource();
                    $resource->id = 'user-' . $i;
                    $resource->companyId = $companyId->value;
                    $resource->loaded = true;
                    $users[] = $resource;
                }
                return $users;
            }
        };

        $this->container->set('collection_provider', $provider);

        $operation = new GetCollection(provider: 'collection_provider');
        $uriVariables = [
            'companyId' => 'tech-corp',
        ];
        $context = [
            'request' => new Request(),
        ];

        $result = $this->decorator->provide($operation, $uriVariables, $context);

        self::assertIsArray($result);
        self::assertCount(3, $result);

        foreach ($result as $index => $item) {
            self::assertInstanceOf(UserResource::class, $item);
            self::assertSame('user-' . ($index + 1), $item->id);
            self::assertSame('tech-corp', $item->companyId);
            self::assertTrue($item->loaded);
        }
    }

    public function testProviderWithIterableReturn(): void
    {
        $provider = new class() {
            public function __invoke(): \Generator
            {
                for ($i = 1; $i <= 5; $i++) {
                    $resource = new UserResource();
                    $resource->id = 'user-' . $i;
                    $resource->loaded = true;
                    yield $resource;
                }
            }
        };

        $this->container->set('iterable_provider', $provider);

        $operation = new GetCollection(provider: 'iterable_provider');
        $uriVariables = [];
        $context = [
            'request' => new Request(),
        ];

        $result = $this->decorator->provide($operation, $uriVariables, $context);

        self::assertIsIterable($result);

        $items = iterator_to_array($result);
        self::assertCount(5, $items);

        foreach ($items as $index => $item) {
            self::assertInstanceOf(UserResource::class, $item);
            self::assertSame('user-' . ($index + 1), $item->id);
            self::assertTrue($item->loaded);
        }
    }

    public function testProviderWithOperationAccess(): void
    {
        $provider = new class() {
            public function __invoke(\ApiPlatform\Metadata\Operation $operation): UserResource
            {
                $resource = new UserResource();
                $resource->loaded = true;
                $resource->name = $operation::class;
                return $resource;
            }
        };

        $this->container->set('operation_provider', $provider);

        $operation = new Get(provider: 'operation_provider');
        $uriVariables = [];
        $context = [
            'request' => new Request(),
        ];

        $result = $this->decorator->provide($operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->loaded);
        self::assertSame(Get::class, $result->name);
    }
}
