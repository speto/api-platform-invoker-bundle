# From Traditional API Platform to Best Practices

This guide shows the evolution from traditional API Platform processors to clean architecture with properly separated concerns using the ApiPlatformInvokerBundle.

## The Journey: From Anti-Patterns to Clean Architecture

### Level 0: Traditional API Platform (The Starting Point)

```php
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;

final class CreateUserProcessor implements ProcessorInterface
{
    public function __construct(
        private UserRepository $repository,
    ) {}

    public function process(
        mixed $data,                    // ❌ No type safety
        Operation $operation, 
        array $uriVariables = [],        // ❌ Array access needed
        array $context = []              // ❌ Complex context array
    ): mixed {                          // ❌ No return type safety
        $companyId = $uriVariables['companyId'] ?? null;  // ❌ Manual extraction
        
        if (!$data instanceof UserResource) {  // ❌ Runtime type checking
            throw new \InvalidArgumentException('Invalid data type');
        }
        
        // ❌ Business logic mixed with HTTP concerns
        // ❌ Direct repository usage in processor
        // ❌ Primitive obsession
        $user = new User(
            id: Uuid::uuid4()->toString(),
            companyId: $companyId,
            email: $data->email,
            name: $data->name,
        );
        
        $this->repository->save($user);
        
        return $data;
    }
}
```

### Level 1: Typed Invokable (Better Types, But Still An Anti-Pattern!)

```php
final readonly class CreateUserAction
{
    public function __construct(
        private UserRepository $repository,  // ⚠️ ANTI-PATTERN: Repository in Action!
    ) {}

    public function __invoke(
        CreateUserInput $data,           // ✅ Typed input
        CompanyId $companyId,            // ✅ Auto-mapped value object
    ): UserOutput {                      // ✅ Typed output
        // ❌ Business logic still in action
        // ❌ Direct repository access
        // ❌ Action doing too much
        $user = new User(
            id: UserId::generate(),
            companyId: $companyId,
            email: Email::fromString($data->email),
            name: $data->name,
        );
        
        $this->repository->save($user);  // ❌ Persistence in action layer
        
        return UserOutput::fromEntity($user);
    }
}
```

**Why this is still wrong:**
- Action layer contains business logic
- Direct dependency on repository (infrastructure)
- No separation of concerns
- Hard to test business logic independently
- Violates Dependency Inversion Principle

### Level 2: Service Layer (Good Practice)

```php
// API Layer - Thin HTTP Adapter
final readonly class CreateUserAction
{
    public function __construct(
        private UserService $userService,  // ✅ Depend on service, not repository
    ) {}

    public function __invoke(
        CreateUserInput $input,
        CompanyId $companyId,
    ): UserOutput {
        // ✅ Action only handles HTTP concerns
        $user = $this->userService->createUser(
            companyId: $companyId,
            email: $input->email,
            name: $input->name,
        );
        
        // ✅ Clean transformation for HTTP response
        return UserOutput::fromDomainModel($user);
    }
}

// Application Layer - Business Logic
final readonly class UserService
{
    public function __construct(
        private UserRepository $userRepository,
        private CompanyRepository $companyRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}
    
    public function createUser(
        CompanyId $companyId,
        string $email,
        string $name,
    ): User {
        // ✅ Business validation
        $company = $this->companyRepository->findById($companyId);
        if (!$company) {
            throw new CompanyNotFoundException($companyId);  // ✅ Domain exception
        }
        
        if ($this->userRepository->emailExists($email)) {
            throw new EmailAlreadyExistsException($email);  // ✅ Domain exception
        }
        
        // ✅ Domain logic
        $user = User::create(
            id: UserId::generate(),
            companyId: $companyId,
            email: Email::fromString($email),
            name: $name,
        );
        
        // ✅ Persistence through repository
        $this->userRepository->save($user);
        
        // ✅ Domain events
        $this->eventDispatcher->dispatch(new UserCreated($user));
        
        return $user;
    }
}
```

### Level 3: Command/Handler Pattern (Best Practice for Complex Domains)

```php
// API Layer - Ultra-thin HTTP Adapter
final readonly class CreateUserAction
{
    public function __construct(
        private CommandBus $commandBus,  // ✅ Only depends on command bus
    ) {}

    public function __invoke(
        CreateUserInput $input,
        CompanyId $companyId,
    ): UserOutput {
        // ✅ Create command from HTTP input
        $command = new CreateUserCommand(
            companyId: $companyId,
            email: $input->email,
            name: $input->name,
        );
        
        // ✅ Dispatch to handler
        $userId = $this->commandBus->dispatch($command);
        
        // ✅ Transform for HTTP response
        return new UserOutput(
            id: $userId->toString(),
            email: $input->email,
            name: $input->name,
            companyId: $companyId->toString(),
        );
    }
}

// Application Layer - Command
final readonly class CreateUserCommand
{
    public function __construct(
        public CompanyId $companyId,
        public string $email,
        public string $name,
    ) {}
}

// Application Layer - Handler
final readonly class CreateUserHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private CompanyRepository $companyRepository,
        private DomainEventPublisher $eventPublisher,
    ) {}
    
    public function handle(CreateUserCommand $command): UserId
    {
        // ✅ Validate business rules
        $this->ensureCompanyExists($command->companyId);
        $this->ensureEmailIsUnique($command->email);
        
        // ✅ Create domain entity
        $user = User::register(
            id: UserId::generate(),
            companyId: $command->companyId,
            email: Email::fromString($command->email),
            name: $command->name,
        );
        
        // ✅ Persist through repository
        $this->userRepository->save($user);
        
        // ✅ Publish domain events
        $this->eventPublisher->publish(
            new UserRegistered(
                userId: $user->getId(),
                companyId: $user->getCompanyId(),
                occurredAt: new \DateTimeImmutable(),
            )
        );
        
        return $user->getId();
    }
    
    private function ensureCompanyExists(CompanyId $companyId): void
    {
        if (!$this->companyRepository->exists($companyId)) {
            throw new CompanyNotFoundException($companyId);
        }
    }
    
    private function ensureEmailIsUnique(string $email): void
    {
        if ($this->userRepository->emailExists($email)) {
            throw new EmailAlreadyExistsException($email);
        }
    }
}
```

## Architecture Layers

```
┌─────────────────────────────────────┐
│         Presentation Layer          │
│  (API Actions - HTTP Adapters)      │
│  • Thin controllers                 │
│  • HTTP status codes                │
│  • Request/Response DTOs            │
└─────────────────────────────────────┘
                 ↓ depends on
┌─────────────────────────────────────┐
│       Application Layer             │
│  (Services/Handlers)                │
│  • Business logic orchestration     │
│  • Transaction boundaries           │
│  • Use case implementation          │
└─────────────────────────────────────┘
                 ↓ depends on
┌─────────────────────────────────────┐
│         Domain Layer                │
│  (Entities, Value Objects)          │
│  • Business rules                   │
│  • Domain events                    │
│  • Invariants                       │
└─────────────────────────────────────┘
                 ↑ implements
┌─────────────────────────────────────┐
│      Infrastructure Layer           │
│  (Repositories, External Services)  │
│  • Database access                  │
│  • External APIs                    │
│  • File systems                     │
└─────────────────────────────────────┘
```

## Key Principles

### 1. Actions Are HTTP Adapters Only

```php
// ✅ GOOD - Action as thin HTTP adapter
final readonly class ProductAction {
    public function __construct(
        private ProductService $service  // Service, not repository
    ) {}
    
    public function __invoke(Input $input): Output {
        try {
            $result = $this->service->handle($input);
            return Output::from($result);
        } catch (DomainException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }
}

// ❌ BAD - Action with business logic
final readonly class ProductAction {
    public function __construct(
        private ProductRepository $repository  // Repository in action!
    ) {}
    
    public function __invoke(Input $input): Output {
        // Business logic in action - WRONG!
        $product = $this->repository->find($input->id);
        $product->updatePrice($input->price);
        $this->repository->save($product);
        return Output::from($product);
    }
}
```

### 2. Rich Domain Models

```php
// ✅ GOOD - Entity with behavior
final class Product {
    public function applyDiscount(Discount $discount): void {
        $newPrice = $discount->apply($this->price);
        
        if ($newPrice->isLessThan($this->minimumPrice)) {
            throw new DiscountTooHighException();
        }
        
        $this->price = $newPrice;
        $this->recordEvent(new DiscountApplied($this->id, $discount));
    }
}

// ❌ BAD - Anemic model with setters
class Product {
    public function setPrice(float $price): void { 
        $this->price = $price;  // No business logic!
    }
}
```

### 3. Proper Exception Handling

```php
// ✅ GOOD - Domain exceptions transformed at boundary
// Domain Layer
throw new ProductNotFoundException($id);  // Domain exception

// API Layer
catch (ProductNotFoundException $e) {
    throw new NotFoundHttpException();  // HTTP exception
}

// ❌ BAD - HTTP exceptions in domain
// Domain Layer
throw new NotFoundHttpException();  // HTTP concern in domain!
```

### 4. Testing Strategy

```php
// ✅ GOOD - Test each layer independently

// Test Action (mock service)
public function testAction(): void
{
    $service = $this->createMock(ProductService::class);
    $service->method('updateProduct')->willReturn($product);
    
    $action = new UpdateProductAction($service);
    // Test only HTTP concerns
}

// Test Service (mock repository)
public function testService(): void
{
    $repository = $this->createMock(ProductRepository::class);
    $service = new ProductService($repository);
    // Test only business logic
}
```

## When to Use Each Pattern

### Simple CRUD → Service Layer
```php
final readonly class ProductAction
{
    public function __construct(private ProductService $service) {}
    
    public function __invoke(CreateProductInput $input): ProductOutput
    {
        $product = $this->service->createProduct($input);
        return ProductOutput::from($product);
    }
}
```

### Complex Business Logic → Command/Handler
```php
final readonly class ProcessOrderAction
{
    public function __construct(private CommandBus $commandBus) {}
    
    public function __invoke(ProcessOrderInput $input): OrderOutput
    {
        $orderId = $this->commandBus->dispatch(
            new ProcessOrderCommand($input)
        );
        return new OrderOutput($orderId);
    }
}
```

## Common Mistakes to Avoid

1. **Repository in Action** - Actions should NEVER inject repositories directly
2. **Fat Controllers** - Actions doing validation, business logic, and persistence
3. **Anemic Domain Models** - Entities with only getters/setters
4. **Wrong Exception Types** - HTTP exceptions in domain layer
5. **Mixed Concerns** - Business logic spread across layers

## Summary

The ApiPlatformInvokerBundle provides excellent type safety and automatic URI variable mapping, but **this is just the beginning**. True best practices require:

✅ **Actions are thin HTTP adapters** - No business logic, no repositories  
✅ **Service/Handler layer** - All business logic lives here  
✅ **Rich domain models** - Entities with behavior, not just data  
✅ **Proper error handling** - Domain exceptions, not HTTP exceptions  
✅ **Clean architecture** - Clear layer separation with proper dependencies  

Remember: **Actions → Services → Repositories**, never **Actions → Repositories**!