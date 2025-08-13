<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Tests\Integration;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProviderInterface;
use PHPUnit\Framework\TestCase;
use Speto\ApiPlatformInvokerBundle\Provider\InvokableProviderDecorator;
use Speto\ApiPlatformInvokerBundle\Provider\ProviderInvoker;
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
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class ProviderOperationResolutionTest extends TestCase
{
    private Container $container;

    private InvokableProviderDecorator $decorator;

    protected function setUp(): void
    {
        $this->container = new Container();

        $uriVarInstantiator = new UriVarInstantiator();
        $uriVarValueResolver = new UriVarValueResolver($uriVarInstantiator);

        $operationResolver = new class() implements ValueResolverInterface {
            public function resolve(
                \Symfony\Component\HttpFoundation\Request $request,
                ArgumentMetadata $argument
            ): iterable {
                if ($argument->getType() === Operation::class
                    || is_subclass_of($argument->getType(), Operation::class)
                ) {
                    $operation = $request->attributes->get('_api_operation');
                    if ($operation instanceof Operation) {
                        $expectedType = $argument->getType();
                        if ($expectedType === Operation::class
                            || (is_string($expectedType) && $operation instanceof $expectedType)) {
                            return [$operation];
                        }
                    }
                    if ($argument->isNullable()) {
                        return [null];
                    }
                }
                return [];
            }
        };

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
                Operation $operation,
                array $uriVariables = [],
                array $context = []
            ): object|array|null {
                return null;
            }
        };

        $this->decorator = new InvokableProviderDecorator($innerProvider, $this->container, $providerInvoker);
    }

    public function testProviderReceivingOperationParameter(): void
    {
        $provider = new class() {
            public function __invoke(Operation $operation): UserResource
            {
                $resource = new UserResource();
                $resource->loaded = true;
                $resource->name = $operation::class;
                $resource->email = $operation->getShortName() ?? 'unknown';
                return $resource;
            }
        };

        $this->container->set('operation_provider', $provider);

        $operation = new Get(provider: 'operation_provider', shortName: 'User');

        $request = new Request();
        $request->attributes->set('_api_operation', $operation);

        $context = [
            'request' => $request,
        ];
        $result = $this->decorator->provide($operation, [], $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->loaded);
        self::assertSame(Get::class, $result->name);
        self::assertSame('User', $result->email);
    }

    public function testProviderWithOperationAndUriVariablesTogether(): void
    {
        $provider = new class() {
            public function __invoke(
                Operation $operation,
                #[MapUriVar('id')]
                StringUserId $userId,
                #[MapUriVar('companyId')]
                CompanyId $companyId
            ): UserResource {
                $resource = new UserResource();
                $resource->loaded = true;
                $resource->id = $userId->value;
                $resource->companyId = $companyId->value;
                $resource->name = $operation::class;
                $resource->email = $operation->getShortName() . '@' . $companyId->value . '.com';
                return $resource;
            }
        };

        $this->container->set('operation_uri_provider', $provider);

        $operation = new Get(provider: 'operation_uri_provider', shortName: 'Employee');
        $uriVariables = [
            'id' => 'user-456',
            'companyId' => 'acme-corp',
        ];

        $request = new Request();
        $request->attributes->set('_api_operation', $operation);

        $context = [
            'request' => $request,
        ];
        $result = $this->decorator->provide($operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->loaded);
        self::assertSame('user-456', $result->id);
        self::assertSame('acme-corp', $result->companyId);
        self::assertSame(Get::class, $result->name);
        self::assertSame('Employee@acme-corp.com', $result->email);
    }

    public function testCollectionOperationWithGetCollection(): void
    {
        $provider = new class() {
            public function __invoke(
                GetCollection $operation,
                #[MapUriVar('companyId')]
                CompanyId $companyId
            ): array {
                $users = [];
                for ($i = 1; $i <= 3; $i++) {
                    $resource = new UserResource();
                    $resource->id = 'user-' . $i;
                    $resource->companyId = $companyId->value;
                    $resource->name = $operation::class;
                    $resource->email = 'user' . $i . '@' . $companyId->value . '.com';
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

        $request = new Request();
        $request->attributes->set('_api_operation', $operation);

        $context = [
            'request' => $request,
        ];
        $result = $this->decorator->provide($operation, $uriVariables, $context);

        self::assertIsArray($result);
        self::assertCount(3, $result);

        foreach ($result as $index => $item) {
            self::assertInstanceOf(UserResource::class, $item);
            self::assertSame('user-' . ($index + 1), $item->id);
            self::assertSame('tech-corp', $item->companyId);
            self::assertSame(GetCollection::class, $item->name);
            self::assertSame('user' . ($index + 1) . '@tech-corp.com', $item->email);
            self::assertTrue($item->loaded);
        }
    }

    public function testSpecificOperationSubclasses(): void
    {
        $provider = new class() {
            public function __invoke(Operation $operation, #[MapUriVar('id')] StringUserId $userId): UserResource
            {
                $resource = new UserResource();
                $resource->loaded = true;
                $resource->id = $userId->value;
                $resource->name = $operation::class;

                $resource->email = match (true) {
                    $operation instanceof Get => 'get-' . $userId->value . '@example.com',
                    $operation instanceof Post => 'post-' . $userId->value . '@example.com',
                    $operation instanceof Delete => 'delete-' . $userId->value . '@example.com',
                    default => 'unknown-' . $userId->value . '@example.com',
                };

                return $resource;
            }
        };

        $this->container->set('polymorphic_provider', $provider);

        $getOperation = new Get(provider: 'polymorphic_provider');
        $request = new Request();
        $request->attributes->set('_api_operation', $getOperation);

        $result = $this->decorator->provide($getOperation, [
            'id' => 'user-123',
        ], [
            'request' => $request,
        ]);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertSame(Get::class, $result->name);
        self::assertSame('get-user-123@example.com', $result->email);

        $postOperation = new Post(provider: 'polymorphic_provider');
        $request = new Request();
        $request->attributes->set('_api_operation', $postOperation);

        $result = $this->decorator->provide($postOperation, [
            'id' => 'user-456',
        ], [
            'request' => $request,
        ]);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertSame(Post::class, $result->name);
        self::assertSame('post-user-456@example.com', $result->email);

        $deleteOperation = new Delete(provider: 'polymorphic_provider');
        $request = new Request();
        $request->attributes->set('_api_operation', $deleteOperation);

        $result = $this->decorator->provide($deleteOperation, [
            'id' => 'user-789',
        ], [
            'request' => $request,
        ]);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertSame(Delete::class, $result->name);
        self::assertSame('delete-user-789@example.com', $result->email);
    }

    public function testNullableOperationParameter(): void
    {
        $provider = new class() {
            public function __invoke(?Operation $operation, #[MapUriVar('id')] StringUserId $userId): UserResource
            {
                $resource = new UserResource();
                $resource->loaded = true;
                $resource->id = $userId->value;
                $resource->name = $operation ? $operation::class : 'no-operation';
                $resource->email = ($operation ? 'with-op' : 'without-op') . '@example.com';
                return $resource;
            }
        };

        $this->container->set('nullable_operation_provider', $provider);

        $operation = new Get(provider: 'nullable_operation_provider');
        $request = new Request();
        $request->attributes->set('_api_operation', $operation);

        $result = $this->decorator->provide($operation, [
            'id' => 'user-123',
        ], [
            'request' => $request,
        ]);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertSame(Get::class, $result->name);
        self::assertSame('with-op@example.com', $result->email);

        $specificProvider = new class() {
            public function __invoke(?GetCollection $operation, #[MapUriVar('id')] StringUserId $userId): UserResource
            {
                $resource = new UserResource();
                $resource->loaded = true;
                $resource->id = $userId->value;
                $resource->name = $operation ? $operation::class : 'no-collection-operation';
                $resource->email = ($operation ? 'collection-op' : 'no-collection-op') . '@example.com';
                return $resource;
            }
        };

        $this->container->set('specific_nullable_provider', $specificProvider);

        $collectionOperation = new GetCollection(provider: 'specific_nullable_provider');
        $request = new Request();
        $request->attributes->set('_api_operation', $collectionOperation);

        $result = $this->decorator->provide($collectionOperation, [
            'id' => 'user-456',
        ], [
            'request' => $request,
        ]);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertSame(GetCollection::class, $result->name);
        self::assertSame('collection-op@example.com', $result->email);

        $getOperation = new Get(provider: 'specific_nullable_provider');
        $request = new Request();
        $request->attributes->set('_api_operation', $getOperation);

        $result = $this->decorator->provide($getOperation, [
            'id' => 'user-789',
        ], [
            'request' => $request,
        ]);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertSame('no-collection-operation', $result->name);
        self::assertSame('no-collection-op@example.com', $result->email);
    }

    public function testProviderAccessingOperationMetadata(): void
    {
        $provider = new class() {
            public function __invoke(Operation $operation): UserResource
            {
                $resource = new UserResource();
                $resource->loaded = true;
                $resource->name = $operation::class;

                $resource->email = ($operation->getShortName() ?? 'unknown') . '@example.com';

                $extraProperties = $operation->getExtraProperties();
                $resource->companyId = $extraProperties['test_property'] ?? 'default';

                return $resource;
            }
        };

        $this->container->set('metadata_provider', $provider);

        $operation = new Get(
            provider: 'metadata_provider',
            shortName: 'TestUser',
            extraProperties: [
                'test_property' => 'metadata-value',
            ]
        );

        $request = new Request();
        $request->attributes->set('_api_operation', $operation);

        $result = $this->decorator->provide($operation, [], [
            'request' => $request,
        ]);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->loaded);
        self::assertSame(Get::class, $result->name);
        self::assertSame('TestUser@example.com', $result->email);
        self::assertSame('metadata-value', $result->companyId);
    }

    public function testProviderWithComplexOperationHandling(): void
    {
        $provider = new class() {
            public function __invoke(
                Operation $operation,
                #[MapUriVar('companyId')]
                CompanyId $companyId
            ): UserResource|array {
                $isCollection = $operation instanceof GetCollection;

                if ($isCollection) {
                    $users = [];
                    $limit = (int) ($operation->getExtraProperties()['limit'] ?? 2);

                    for ($i = 1; $i <= $limit; $i++) {
                        $resource = new UserResource();
                        $resource->id = 'user-' . $i;
                        $resource->companyId = $companyId->value;
                        $resource->name = 'collection-item';
                        $resource->loaded = true;
                        $users[] = $resource;
                    }
                    return $users;
                }
                $resource = new UserResource();
                $resource->loaded = true;
                $resource->companyId = $companyId->value;
                $resource->name = 'single-item';
                $resource->email = $operation->getShortName() . '@' . $companyId->value . '.com';
                return $resource;

            }
        };

        $this->container->set('complex_provider', $provider);

        $collectionOperation = new GetCollection(
            provider: 'complex_provider',
            extraProperties: [
                'limit' => 3,
            ]
        );
        $request = new Request();
        $request->attributes->set('_api_operation', $collectionOperation);

        $result = $this->decorator->provide(
            $collectionOperation,
            [
                'companyId' => 'test-corp',
            ],
            [
                'request' => $request,
            ]
        );

        self::assertIsArray($result);
        self::assertCount(3, $result);
        foreach ($result as $item) {
            self::assertInstanceOf(UserResource::class, $item);
            self::assertSame('collection-item', $item->name);
            self::assertSame('test-corp', $item->companyId);
        }

        $getOperation = new Get(provider: 'complex_provider', shortName: 'Employee');
        $request = new Request();
        $request->attributes->set('_api_operation', $getOperation);

        $result = $this->decorator->provide(
            $getOperation,
            [
                'companyId' => 'test-corp',
            ],
            [
                'request' => $request,
            ]
        );

        self::assertInstanceOf(UserResource::class, $result);
        self::assertSame('single-item', $result->name);
        self::assertSame('Employee@test-corp.com', $result->email);
        self::assertSame('test-corp', $result->companyId);
    }

    public function testProviderWithOperationParameterOrdering(): void
    {
        $provider = new class() {
            public function __invoke(
                #[MapUriVar('id')]
                StringUserId $userId,
                Operation $operation,
                #[MapUriVar('companyId')]
                CompanyId $companyId,
                Request $request
            ): UserResource {
                $resource = new UserResource();
                $resource->loaded = true;
                $resource->id = $userId->value;
                $resource->companyId = $companyId->value;
                $resource->name = $operation::class;
                $resource->hasRequest = true;
                return $resource;
            }
        };

        $this->container->set('ordered_provider', $provider);

        $operation = new Get(provider: 'ordered_provider');
        $request = new Request();
        $request->attributes->set('_api_operation', $operation);

        $result = $this->decorator->provide(
            $operation,
            [
                'id' => 'user-123',
                'companyId' => 'test-corp',
            ],
            [
                'request' => $request,
            ]
        );

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->loaded);
        self::assertTrue($result->hasRequest);
        self::assertSame('user-123', $result->id);
        self::assertSame('test-corp', $result->companyId);
        self::assertSame(Get::class, $result->name);
    }

    public function testProviderWithOperationInterfaceTypeHint(): void
    {
        $provider = new class() {
            public function __invoke(Operation $operation): UserResource
            {
                $resource = new UserResource();
                $resource->loaded = true;

                $resource->name = match (true) {
                    $operation instanceof GetCollection => 'collection',
                    $operation instanceof Get => 'item',
                    $operation instanceof Post => 'create',
                    default => 'other',
                };

                $resource->email = $operation::class;
                return $resource;
            }
        };

        $this->container->set('interface_provider', $provider);

        $operations = [
            new Get(provider: 'interface_provider'),
            new GetCollection(provider: 'interface_provider'),
            new Post(provider: 'interface_provider'),
        ];

        $expectedNames = ['item', 'collection', 'create'];

        foreach ($operations as $index => $operation) {
            $request = new Request();
            $request->attributes->set('_api_operation', $operation);

            $result = $this->decorator->provide($operation, [], [
                'request' => $request,
            ]);

            self::assertInstanceOf(UserResource::class, $result);
            self::assertTrue($result->loaded);
            self::assertSame($expectedNames[$index], $result->name);
            self::assertSame($operation::class, $result->email);
        }
    }
}
