<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Tests\Integration;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use PHPUnit\Framework\TestCase;
use Speto\ApiPlatformInvokerBundle\Processor\ActionInvoker;
use Speto\ApiPlatformInvokerBundle\Processor\DataValueResolver;
use Speto\ApiPlatformInvokerBundle\Processor\InvokableProcessorDecorator;
use Speto\ApiPlatformInvokerBundle\Tests\Fixtures\Processors\TestInvokableProcessor;
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

final class EndToEndProcessorFlowTest extends TestCase
{
    private Container $container;

    private InvokableProcessorDecorator $decorator;

    protected function setUp(): void
    {
        $this->container = new Container();

        $uriVarInstantiator = new UriVarInstantiator();
        $uriVarValueResolver = new UriVarValueResolver($uriVarInstantiator);

        $argumentResolver = new ArgumentResolver(null, [
            new DataValueResolver(),
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

        $this->container->set('test_processor', new TestInvokableProcessor());
    }

    public function testCompleteFlowWithInvokableProcessor(): void
    {
        $userData = new UserResource();
        $userData->name = 'John Doe';
        $userData->email = 'john@example.com';

        $operation = new Post(processor: 'test_processor');

        $uriVariables = [
            'companyId' => 'company-123',
        ];

        $request = new Request();

        $context = [
            'request' => $request,
        ];

        $result = $this->decorator->process($userData, $operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->processed);
        self::assertSame('John Doe', $result->name);
        self::assertSame('john@example.com', $result->email);
        self::assertSame('company-123', $result->companyId);
    }

    public function testCompleteFlowWithMultipleUriVariables(): void
    {
        $processor = new class() {
            public function __invoke(
                UserResource $data,
                #[MapUriVar('companyId')]
                CompanyId $companyId,
                #[MapUriVar('departmentId')]
                string $departmentId,
                Request $request
            ): UserResource {
                $data->companyId = $companyId->value;
                $data->email = $departmentId . '@' . $companyId->value . '.com';
                $data->processed = true;
                return $data;
            }
        };

        $this->container->set('multi_var_processor', $processor);

        $userData = new UserResource();
        $userData->name = 'Jane Doe';

        $operation = new Post(processor: 'multi_var_processor');
        $uriVariables = [
            'companyId' => 'acme-corp',
            'departmentId' => 'engineering',
        ];

        $request = new Request();
        $context = [
            'request' => $request,
        ];

        $result = $this->decorator->process($userData, $operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->processed);
        self::assertSame('acme-corp', $result->companyId);
        self::assertSame('engineering@acme-corp.com', $result->email);
    }

    public function testFallbackToInnerProcessorWhenNotInvokable(): void
    {
        $traditionalProcessor = new class() implements ProcessorInterface {
            public function process(
                mixed $data,
                \ApiPlatform\Metadata\Operation $operation,
                array $uriVariables = [],
                array $context = []
            ): mixed {
                if ($data instanceof UserResource) {
                    $data->processed = true;
                    $data->email = 'traditional@example.com';
                }
                return $data;
            }
        };

        $this->container->set('traditional_processor', $traditionalProcessor);

        $userData = new UserResource();
        $userData->name = 'Traditional User';

        $operation = new Post(processor: 'traditional_processor');
        $uriVariables = [];
        $context = [
            'request' => new Request(),
        ];

        $result = $this->decorator->process($userData, $operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertSame('Traditional User', $result->name);
        self::assertFalse($result->processed);
    }

    public function testProcessorWithoutUriVariables(): void
    {
        $processor = new class() {
            public function __invoke(UserResource $data, Request $request): UserResource
            {
                $data->processed = true;
                $data->email = 'no-uri-vars@example.com';
                return $data;
            }
        };

        $this->container->set('no_uri_processor', $processor);

        $userData = new UserResource();
        $userData->name = 'No URI User';

        $operation = new Post(processor: 'no_uri_processor');
        $uriVariables = [];
        $context = [
            'request' => new Request(),
        ];

        $result = $this->decorator->process($userData, $operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->processed);
        self::assertSame('no-uri-vars@example.com', $result->email);
    }

    public function testProcessorWithServiceInjection(): void
    {
        $mockService = new class() {
            public function transform(string $value): string
            {
                return strtoupper($value);
            }
        };

        $processor = new class($mockService) {
            public function __construct(
                private $transformer
            ) {
            }

            public function __invoke(
                UserResource $data,
                #[MapUriVar('companyId')]
                CompanyId $companyId
            ): UserResource {
                $data->companyId = $this->transformer->transform($companyId->value);
                $data->processed = true;
                return $data;
            }
        };

        $this->container->set('service_injection_processor', $processor);

        $userData = new UserResource();
        $userData->name = 'Service User';

        $operation = new Post(processor: 'service_injection_processor');
        $uriVariables = [
            'companyId' => 'lowercase-company',
        ];
        $context = [
            'request' => new Request(),
        ];

        $result = $this->decorator->process($userData, $operation, $uriVariables, $context);

        self::assertInstanceOf(UserResource::class, $result);
        self::assertTrue($result->processed);
        self::assertSame('LOWERCASE-COMPANY', $result->companyId);
    }
}
