# ApiPlatformInvokerBundle

Invokable processors + typed URI var mapping for API Platform (3.3+) using Symfony's ArgumentResolver.

## Features

- ✅ Use simple callable classes as API Platform processors (no need to implement `ProcessorInterface`)
- ✅ Automatic URI variable to value object conversion
- ✅ Full Symfony dependency injection support
- ✅ Type-safe parameter resolution
- ✅ Multiple constructor patterns support (constructor, static factories, named constructors)

## Installation

```bash
composer require speto/api-platform-invoker-bundle
```

Enable the bundle:

```php
// config/bundles.php
return [
    // ...
    Speto\ApiPlatformInvokerBundle\ApiPlatformInvokerBundle::class => ['all' => true],
];
```

## Example

### Clean Invokable Approach (With This Bundle)
✅ **Type-safe and clean** - What this bundle enables

```php
use ApiPlatform\Metadata\{ApiResource, Post, Link};
use Speto\ApiPlatformInvokerBundle\UriVar\Attribute\MapUriVar; // Optional - automatic mapping works too!

// 1️⃣ Resource configuration
#[ApiResource(operations: [
    new Post(
        uriTemplate: '/companies/{companyId}/users',
        uriVariables: ['companyId' => new Link(fromClass: CompanyResource::class)],
        input: RegisterUserInput::class,
        output: UserOutput::class,
        processor: RegisterUserAction::class, // ✅ Simple invokable class
    ),
])]
final class UserResource {}

// 2️⃣ Invokable processor - No ProcessorInterface needed!
final readonly class RegisterUserAction
{
    public function __construct(
        private RegistersUsers $handler,      // ✅ Domain handler interface
    ) {}

    public function __invoke(
        RegisterUserInput $input,             // ✅ Type-safe input
        #[MapUriVar] CompanyId $companyId,    // ✅ Auto-converted from string (or automatic by name match!)
    ): UserOutput {                           // ✅ Type-safe output
        // ✅ Create domain command
        $command = new RegisterUser(
            companyId: $companyId,
            email: Email::fromString($input->email),
            name: $input->name,
        );

        // ✅ Delegate to domain layer
        $userId = ($this->handler)($command);

        // ✅ Clean output conversion
        return new UserOutput(
            id: $userId->toString(),
            email: $input->email,
            name: $input->name,
            companyId: $companyId->toString(),
        );
    }
}

// 3️⃣ Input DTO - Clear contract
final readonly class RegisterUserInput
{
    public function __construct(
        public string $email,
        public string $name,
    ) {}
}

// 4️⃣ Output DTO - No domain leakage
final readonly class UserOutput
{
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
        public string $companyId,
    ) {}
}
```

### Traditional API Platform Approach
❌ **Verbose and not type-safe** - The standard way without this bundle

```php
use ApiPlatform\Metadata\{ApiResource, Post, Operation};
use ApiPlatform\State\ProcessorInterface;

#[ApiResource(operations: [
    new Post(
        uriTemplate: '/companies/{companyId}/users',
        input: UserResource::class,
        processor: RegisterUserProcessor::class,
    ),
])]
final class UserResource
{
    public ?string $id = null;
    public string $email;
    public string $name;
}

final class RegisterUserProcessor implements ProcessorInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function process(
        mixed $data,                    // ❌ No type safety
        Operation $operation, 
        array $uriVariables = [],        // ❌ Array access needed
        array $context = []              // ❌ Complex context array
    ): mixed {                           // ❌ No return type safety
        // ❌ Manual type checking needed
        if (!$data instanceof UserResource) {
            throw new \InvalidArgumentException('Invalid data type');
        }
        
        // ❌ Manual URI variable extraction and conversion
        $companyId = new CompanyId($uriVariables['companyId'] ?? throw new \RuntimeException('Company ID required'));
        
        // ❌ Business logic mixed with API concerns
        $user = new User(
            id: UserId::generate(),
            companyId: $companyId,
            email: $data->email,
            name: $data->name,
        );
        
        $this->userRepository->save($user);
        $this->eventDispatcher->dispatch(new UserRegistered($user->getId()));
        
        return UserResource::fromEntity($user);
    }
}
```

### Pattern Comparison

| Aspect | ❌ Traditional API Platform | ✅ With This Bundle |
|--------|------------------------------|---------------------|
| **Interface Required** | ProcessorInterface | None - just __invoke() |
| **Method Signature** | process(mixed $data, Operation $op, array $uriVars, array $context) | Clean __invoke() with typed params |
| **Type Safety** | mixed input/output | Full type safety |
| **URI Variables** | Manual array access & conversion | Auto-converted via #[MapUriVar] or name matching |
| **IDE Support** | Limited - arrays and mixed types | Full autocomplete & type hints |
| **Boilerplate Code** | Interface, type checks, conversions | Minimal - just your logic |
| **Testing** | Complex mocking of arrays/context | Simple, type-safe mocks |
| **Code Lines** | ~25 lines of boilerplate | ~10 lines of actual logic |

**🚀 Evolution Path**: Start with basic usage to learn the bundle, then evolve to clean architecture patterns as your application grows. See the [Progression Guide](docs/progression-guide.md) for detailed migration strategies.

## Documentation

For detailed examples and advanced usage, see the documentation:

- **[Progression Guide](docs/progression-guide.md)** - Evolution from basic patterns to DDD, migration strategy, when to use each approach
- **[Clean Architecture & DDD](docs/clean-architecture.md)** - Domain-driven design patterns, layer separation, proper DTOs
- **[Advanced Examples](docs/advanced-examples.md)** - Multiple URI variables, custom constructors, complex business logic
- **[Complete CRUD Examples](docs/crud-examples.md)** - Full Create, Read, Update, Delete operations with nested resources
- **[Symfony Integration](docs/symfony-integration.md)** - Error handling, event integration, cache, messenger, custom resolvers
- **[Architecture & Internals](docs/architecture.md)** - How the bundle works under the hood, extension points, performance

## How It Works

1. **Processor Detection**: The bundle decorates API Platform's state processor to detect invokable processors
2. **Argument Resolution**: Uses Symfony's ArgumentResolver to resolve all parameters automatically
3. **URI Variable Mapping**: The `#[MapUriVar]` attribute maps URI variables to typed value objects
4. **Type Conversion**: Automatically converts string URI variables to your domain value objects
5. **Dependency Injection**: Full support for constructor injection of services

## Benefits

- **Cleaner Code**: No need to implement `ProcessorInterface` or handle `$context` arrays
- **Type Safety**: Full IDE autocomplete and static analysis support
- **Testability**: Easy to unit test with mock dependencies
- **Reusability**: Processors can be reused as regular services
- **Flexibility**: Use any Symfony service or request data via ArgumentResolver

## Requirements

- PHP 8.3+
- Symfony 7.1+
- API Platform 3.3+

## License

MIT