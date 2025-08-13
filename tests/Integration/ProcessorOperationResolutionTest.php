<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Tests\Integration;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use PHPUnit\Framework\TestCase;
use Speto\ApiPlatformInvokerBundle\Common\OperationValueResolver;
use Speto\ApiPlatformInvokerBundle\Processor\ActionInvoker;
use Speto\ApiPlatformInvokerBundle\Processor\DataValueResolver;
use Speto\ApiPlatformInvokerBundle\Processor\InvokableProcessorDecorator;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\Resources\UserResource;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects\CompanyId;
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

final class ProcessorOperationResolutionTest extends TestCase
{
    private Container $container;

    private InvokableProcessorDecorator $decorator;

    protected function setUp(): void
    {
        $this->container = new Container();

        $uriVarInstantiator = new UriVarInstantiator();
        $uriVarValueResolver = new UriVarValueResolver($uriVarInstantiator);

        $operationValueResolver = new OperationValueResolver();

        $argumentResolver = new ArgumentResolver(null, [
            new DataValueResolver(),
            $operationValueResolver,
            $uriVarValueResolver,
            new RequestAttributeValueResolver(),
            new RequestValueResolver(),
            new DefaultValueResolver(),
            new VariadicValueResolver(),
        ]);

        $actionInvoker = new ActionInvoker($argumentResolver);

        $innerProcessor = new class() implements ProcessorInterface {
            public function process(
                mixed $data,
                \ApiPlatform\Metadata\Operation $operation,
                array $uriVariables = [],
                array $context = []
            ): mixed {
                return $data;
            }
        };

        $this->decorator = new InvokableProcessorDecorator($innerProcessor, $this->container, $actionInvoker);
    }

    public function testProcessorReceivingOperationParameterWithData(): void
    {
        $processor = new class() {
            public function __invoke(UserResource $data, Operation $operation): UserResource
            {
                $data->processed = true;
                $data->companyId = $operation->getName() ?? 'operation-test';
                return $data;
            }
        };

        $this->container->set('operation_processor', $processor);

        $userData = new UserResource();
        $userData->name = 'John Doe';

        $operation = new Post(name: 'user_create', processor: 'operation_processor');
        $uriVariables = [];

        $request = new Request();
        $request->attributes->set('_api_operation', $operation);

        $context = [
            'request' => $request,
        ];

        $result = $this->decorator->process($userData, $operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->processed);
        self::assertSame('user_create', $result->companyId);
        self::assertSame('John Doe', $result->name);
    }

    public function testProcessorWithOperationDataAndUriVariables(): void
    {
        $processor = new class() {
            public function __invoke(
                UserResource $data,
                Operation $operation,
                #[MapUriVar('companyId')]
                CompanyId $companyId,
                #[MapUriVar('departmentId')]
                string $departmentId
            ): UserResource {
                $data->processed = true;
                $data->companyId = $companyId->value;
                $data->email = $departmentId . '@' . ($operation->getName() ?? 'unknown') . '.com';
                return $data;
            }
        };

        $this->container->set('full_processor', $processor);

        $userData = new UserResource();
        $userData->name = 'Jane Smith';

        $operation = new Post(name: 'user_department_create', processor: 'full_processor');
        $uriVariables = [
            'companyId' => 'acme-corp',
            'departmentId' => 'engineering',
        ];

        $request = new Request();
        $request->attributes->set('_api_operation', $operation);

        $context = [
            'request' => $request,
        ];

        $result = $this->decorator->process($userData, $operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->processed);
        self::assertSame('acme-corp', $result->companyId);
        self::assertSame('engineering@user_department_create.com', $result->email);
        self::assertSame('Jane Smith', $result->name);
    }

    public function testProcessorWithSpecificGetOperationSubclass(): void
    {
        $processor = new class() {
            public function __invoke(UserResource $data, Get $operation): UserResource
            {
                $data->processed = true;
                $data->email = 'get-operation@' . ($operation->getName() ?? 'test') . '.com';
                return $data;
            }
        };

        $this->container->set('get_processor', $processor);

        $userData = new UserResource();
        $userData->name = 'Get User';

        $operation = new Get(name: 'user_get', processor: 'get_processor');
        $uriVariables = [];

        $request = new Request();
        $request->attributes->set('_api_operation', $operation);

        $context = [
            'request' => $request,
        ];

        $result = $this->decorator->process($userData, $operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->processed);
        self::assertSame('get-operation@user_get.com', $result->email);
    }

    public function testProcessorWithSpecificPostOperationSubclass(): void
    {
        $processor = new class() {
            public function __invoke(UserResource $data, Post $operation): UserResource
            {
                $data->processed = true;
                $data->companyId = 'post-' . ($operation->getName() ?? 'unnamed');
                return $data;
            }
        };

        $this->container->set('post_processor', $processor);

        $userData = new UserResource();
        $userData->name = 'Post User';

        $operation = new Post(name: 'user_create', processor: 'post_processor');
        $uriVariables = [];

        $request = new Request();
        $request->attributes->set('_api_operation', $operation);

        $context = [
            'request' => $request,
        ];

        $result = $this->decorator->process($userData, $operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->processed);
        self::assertSame('post-user_create', $result->companyId);
    }

    public function testProcessorWithSpecificPatchOperationSubclass(): void
    {
        $processor = new class() {
            public function __invoke(UserResource $data, Patch $operation): UserResource
            {
                $data->processed = true;
                $data->email = 'patched@' . ($operation->getName() ?? 'test') . '.org';
                return $data;
            }
        };

        $this->container->set('patch_processor', $processor);

        $userData = new UserResource();
        $userData->name = 'Patch User';

        $operation = new Patch(name: 'user_patch', processor: 'patch_processor');
        $uriVariables = [];

        $request = new Request();
        $request->attributes->set('_api_operation', $operation);

        $context = [
            'request' => $request,
        ];

        $result = $this->decorator->process($userData, $operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->processed);
        self::assertSame('patched@user_patch.org', $result->email);
    }

    public function testProcessorWithSpecificPutOperationSubclass(): void
    {
        $processor = new class() {
            public function __invoke(UserResource $data, Put $operation): UserResource
            {
                $data->processed = true;
                $data->companyId = 'put-updated';
                return $data;
            }
        };

        $this->container->set('put_processor', $processor);

        $userData = new UserResource();
        $userData->name = 'Put User';

        $operation = new Put(name: 'user_put', processor: 'put_processor');
        $uriVariables = [];

        $request = new Request();
        $request->attributes->set('_api_operation', $operation);

        $context = [
            'request' => $request,
        ];

        $result = $this->decorator->process($userData, $operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->processed);
        self::assertSame('put-updated', $result->companyId);
    }

    public function testProcessorWithSpecificDeleteOperationSubclass(): void
    {
        $processor = new class() {
            public function __invoke(UserResource $data, Delete $operation): UserResource
            {
                $data->processed = true;
                $data->email = 'deleted@system.com';
                return $data;
            }
        };

        $this->container->set('delete_processor', $processor);

        $userData = new UserResource();
        $userData->name = 'Delete User';

        $operation = new Delete(name: 'user_delete', processor: 'delete_processor');
        $uriVariables = [];

        $request = new Request();
        $request->attributes->set('_api_operation', $operation);

        $context = [
            'request' => $request,
        ];

        $result = $this->decorator->process($userData, $operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->processed);
        self::assertSame('deleted@system.com', $result->email);
    }

    public function testProcessorAccessingOperationMetadata(): void
    {
        $processor = new class() {
            public function __invoke(UserResource $data, Operation $operation): UserResource
            {
                $data->processed = true;
                $data->name = $operation->getClass() ?? 'no-class';
                $data->email = ($operation->getName() ?? 'no-name') . '@metadata.com';
                $data->companyId = get_class($operation);
                return $data;
            }
        };

        $this->container->set('metadata_processor', $processor);

        $userData = new UserResource();
        $userData->name = 'Original Name';

        $operation = new Post(
            name: 'user_metadata_test',
            class: 'App\\Entity\\User',
            processor: 'metadata_processor'
        );
        $uriVariables = [];

        $request = new Request();
        $request->attributes->set('_api_operation', $operation);

        $context = [
            'request' => $request,
        ];

        $result = $this->decorator->process($userData, $operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->processed);
        self::assertSame('App\\Entity\\User', $result->name);
        self::assertSame('user_metadata_test@metadata.com', $result->email);
        self::assertSame(Post::class, $result->companyId);
    }

    public function testProcessorWithNullableOperationParameter(): void
    {
        $processor = new class() {
            public function __invoke(UserResource $data, ?Operation $operation = null): UserResource
            {
                $data->processed = true;
                if ($operation !== null) {
                    $data->email = ($operation->getName() ?? 'unnamed') . '@nullable.com';
                } else {
                    $data->email = 'no-operation@nullable.com';
                }
                return $data;
            }
        };

        $this->container->set('nullable_processor', $processor);

        $userData = new UserResource();
        $userData->name = 'Nullable Test';

        $operation = new Get(name: 'nullable_test', processor: 'nullable_processor');
        $uriVariables = [];

        $request = new Request();
        $request->attributes->set('_api_operation', $operation);

        $context = [
            'request' => $request,
        ];

        $result = $this->decorator->process($userData, $operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->processed);
        self::assertSame('nullable_test@nullable.com', $result->email);
    }

    public function testProcessorWithNullableOperationWhenNoOperationAvailable(): void
    {
        $processor = new class() {
            public function __invoke(UserResource $data, ?Operation $operation = null): UserResource
            {
                $data->processed = true;
                if ($operation !== null) {
                    $data->email = ($operation->getName() ?? 'unnamed') . '@nullable.com';
                } else {
                    $data->email = 'no-operation@nullable.com';
                }
                return $data;
            }
        };

        $this->container->set('nullable_no_op_processor', $processor);

        $userData = new UserResource();
        $userData->name = 'No Operation Test';

        $operation = new Get(name: 'no_operation_test', processor: 'nullable_no_op_processor');
        $uriVariables = [];

        $request = new Request();

        $context = [
            'request' => $request,
        ];

        $result = $this->decorator->process($userData, $operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->processed);
        self::assertSame('no_operation_test@nullable.com', $result->email);
    }

    public function testProcessorWithMultipleParameterTypesIncludingOperation(): void
    {
        $processor = new class() {
            public function __invoke(
                UserResource $data,
                Operation $operation,
                Request $request,
                #[MapUriVar('companyId')]
                CompanyId $companyId
            ): UserResource {
                $data->processed = true;
                $data->name = 'Processed by ' . get_class($operation);
                $data->email = ($operation->getName() ?? 'unnamed') . '@multi-param.com';
                $data->companyId = $companyId->value;
                return $data;
            }
        };

        $this->container->set('multi_param_processor', $processor);

        $userData = new UserResource();
        $userData->name = 'Multi Param Test';

        $operation = new Patch(name: 'multi_param_test', processor: 'multi_param_processor');
        $uriVariables = [
            'companyId' => 'multi-company',
        ];

        $request = new Request();
        $request->attributes->set('_api_operation', $operation);

        $context = [
            'request' => $request,
        ];

        $result = $this->decorator->process($userData, $operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->processed);
        self::assertSame('Processed by ' . Patch::class, $result->name);
        self::assertSame('multi_param_test@multi-param.com', $result->email);
        self::assertSame('multi-company', $result->companyId);
    }

    public function testProcessorWithOperationSubclassMismatch(): void
    {
        $processor = new class() {
            public function __invoke(UserResource $data, Post $operation): UserResource
            {
                $data->processed = true;
                $data->email = 'post-specific@test.com';
                return $data;
            }
        };

        $this->container->set('post_only_processor', $processor);

        $userData = new UserResource();
        $userData->name = 'Mismatch Test';

        $operation = new Get(name: 'mismatch_test', processor: 'post_only_processor');
        $uriVariables = [];

        $request = new Request();
        $request->attributes->set('_api_operation', $operation);

        $context = [
            'request' => $request,
        ];

        try {
            $result = $this->decorator->process($userData, $operation, $uriVariables, $context);

            self::assertInstanceOf(UserResource::class, $result);
        } catch (\Exception $e) {
            self::assertInstanceOf(\Exception::class, $e);
        }
    }

    public function testProcessorWithCustomOperationSubclass(): void
    {
        $customOperation = new class(name: 'custom_test', processor: 'custom_processor') extends Operation {
            public function __construct(string $name, string $processor)
            {
                parent::__construct();
                $this->name = $name;
                $this->processor = $processor;
            }
        };

        $processor = new class() {
            public function __invoke(UserResource $data, Operation $operation): UserResource
            {
                $data->processed = true;
                $data->companyId = 'custom-' . get_class($operation);
                $data->email = ($operation->getName() ?? 'unnamed') . '@custom.com';
                return $data;
            }
        };

        $this->container->set('custom_processor', $processor);

        $userData = new UserResource();
        $userData->name = 'Custom Operation Test';

        $uriVariables = [];

        $request = new Request();
        $request->attributes->set('_api_operation', $customOperation);

        $context = [
            'request' => $request,
        ];

        $result = $this->decorator->process($userData, $customOperation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->processed);
        self::assertSame('custom_test@custom.com', $result->email);
        self::assertStringContainsString('custom-', $result->companyId);
    }
}
