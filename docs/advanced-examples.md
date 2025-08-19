# Advanced Examples

This document demonstrates all the capabilities and features of the ApiPlatformInvokerBundle.

## Automatic URI Variable Mapping (No Attributes Needed)

The bundle automatically maps URI variables to method parameters when their names match:

```php
use ApiPlatform\Metadata\{ApiResource, Get};

#[ApiResource(operations: [
    new Get(
        uriTemplate: '/users/{userId}/posts/{postId}',
        processor: GetUserPostAction::class,
    ),
])]
final class PostResource {}

final readonly class GetUserPostAction
{
    public function __construct(
        private PostRepository $repository,
    ) {}

    public function __invoke(
        UserId $userId,    // ✨ Automatically mapped from {userId}
        PostId $postId,    // ✨ Automatically mapped from {postId}
    ): PostResource {
        // No #[MapUriVar] attribute needed when names match!
        $post = $this->repository->findByUserAndId($userId, $postId);
        
        if (!$post) {
            throw new NotFoundHttpException('Post not found');
        }
        
        return PostResource::fromEntity($post);
    }
}
```

## Explicit URI Variable Mapping

Use `#[MapUriVar]` when you need different parameter names:

```php
use Speto\ApiPlatformInvokerBundle\UriVar\Attribute\MapUriVar;

final readonly class UpdatePostAction
{
    public function __invoke(
        PostInput $data,
        #[MapUriVar('userId')] UserId $authorId,  // Maps {userId} to $authorId
        #[MapUriVar('postId')] PostId $id,        // Maps {postId} to $id
    ): PostOutput {
        // Your logic here
    }
}
```

## Multiple URI Variables

Handle complex nested routes with multiple typed parameters:

```php
#[ApiResource(operations: [
    new Get(
        uriTemplate: '/departments/{departmentId}/teams/{teamId}/members/{memberId}',
        processor: GetTeamMemberAction::class,
    ),
])]
final class TeamMemberResource {}

final readonly class GetTeamMemberAction
{
    public function __construct(
        private TeamMemberRepository $repository,
    ) {}

    public function __invoke(
        DepartmentId $departmentId,  // Auto-mapped from {departmentId}
        TeamId $teamId,               // Auto-mapped from {teamId}
        MemberId $memberId,           // Auto-mapped from {memberId}
    ): TeamMemberResource {
        $member = $this->repository->findByIds($departmentId, $teamId, $memberId);
        
        if (!$member) {
            throw new NotFoundHttpException('Team member not found');
        }
        
        return TeamMemberResource::fromEntity($member);
    }
}
```

## Value Object Construction Methods

The bundle supports multiple ways to construct value objects from URI string variables:

### Method 1: Regular Constructor

```php
final readonly class UserId
{
    public function __construct(public string $value)
    {
        if (!uuid_is_valid($value)) {
            throw new \InvalidArgumentException('Invalid UUID');
        }
    }
}

// Usage in processor - automatic construction
public function __invoke(UserId $userId): Response {
    // $userId is created via: new UserId($uriVariable)
}
```

### Method 2: Static Factory Method (Auto-detected)

The bundle automatically detects and uses these static methods (in order of priority):
- `fromString()`
- `fromValue()`
- `from()`

```php
final readonly class ProductSku
{
    private function __construct(public string $value) {}
    
    // This will be auto-detected and used
    public static function fromString(string $value): self
    {
        if (!preg_match('/^[A-Z]{3}-\d{4}$/', $value)) {
            throw new \InvalidArgumentException('Invalid SKU format');
        }
        return new self(strtoupper($value));
    }
}

// Usage - automatically uses fromString()
public function __invoke(ProductSku $productSku): Response {
    // $productSku is created via: ProductSku::fromString($uriVariable)
}
```

### Method 3: Custom Constructor Method via Attribute

Specify a custom static method using the `#[UriVarConstructor]` attribute:

```php
use Speto\ApiPlatformInvokerBundle\UriVar\Attribute\UriVarConstructor;

#[UriVarConstructor('fromUrl')]
final readonly class OrderNumber
{
    private function __construct(public string $value) {}
    
    // This method will be used due to the attribute
    public static function fromUrl(string $value): self
    {
        if (!preg_match('/^ORD-\d{6}$/', $value)) {
            throw new \InvalidArgumentException('Invalid order number format');
        }
        return new self($value);
    }
    
    // Other factory methods that won't be used for URI variables
    public static function fromDatabase(int $id): self
    {
        return new self('ORD-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT));
    }
    
    public static function generate(): self
    {
        return new self('ORD-' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT));
    }
}

// Usage - uses fromUrl() as specified by attribute
public function __invoke(OrderNumber $orderNumber): Response {
    // $orderNumber is created via: OrderNumber::fromUrl($uriVariable)
}
```

## Using Both Providers and Processors

The bundle works with both API Platform providers (for GET operations) and processors (for POST/PUT/DELETE):

### Provider Example (GET Operations)

```php
#[ApiResource(operations: [
    new Get(
        uriTemplate: '/companies/{companyId}/users/{userId}',
        provider: GetUserAction::class,  // Note: provider, not processor
    ),
])]
final class UserResource {}

final readonly class GetUserAction
{
    public function __construct(
        private UserRepository $repository,
    ) {}

    public function __invoke(
        CompanyId $companyId,  // Auto-mapped
        UserId $userId,        // Auto-mapped
    ): UserOutput {
        $user = $this->repository->findByCompanyAndId($companyId, $userId);
        
        if (!$user) {
            throw new NotFoundHttpException('User not found');
        }
        
        return UserOutput::fromEntity($user);
    }
}
```

### Processor Example (POST/PUT/DELETE Operations)

```php
#[ApiResource(operations: [
    new Post(
        uriTemplate: '/companies/{companyId}/users',
        input: CreateUserInput::class,
        processor: CreateUserAction::class,  // Note: processor for write operations
    ),
])]
final class UserResource {}

final readonly class CreateUserAction
{
    public function __construct(
        private UserRepository $repository,
    ) {}

    public function __invoke(
        CreateUserInput $data,   // Input from request body
        CompanyId $companyId,    // Auto-mapped from URI
    ): UserOutput {
        $user = new User(
            id: UserId::generate(),
            companyId: $companyId,
            email: $data->email,
            name: $data->name,
        );
        
        $this->repository->save($user);
        
        return UserOutput::fromEntity($user);
    }
}
```

## Mixed Parameter Sources

Combine different parameter sources in a single processor:

```php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use ApiPlatform\Metadata\Operation;

final readonly class ComplexAction
{
    public function __construct(
        private OrderService $orderService,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(
        OrderInput $data,              // From request body
        CompanyId $companyId,          // Auto-mapped from {companyId}
        OrderId $orderId,              // Auto-mapped from {orderId}
        Request $request,              // Symfony Request object
        ?UserInterface $user,          // Current authenticated user (nullable)
        Operation $operation,          // API Platform operation metadata
    ): OrderOutput {
        // Log the action
        $this->logger->info('Processing order', [
            'order_id' => $orderId->toString(),
            'company_id' => $companyId->toString(),
            'user' => $user?->getUserIdentifier(),
            'ip' => $request->getClientIp(),
        ]);
        
        // Process the order
        $order = $this->orderService->updateOrder(
            $orderId,
            $companyId,
            $data
        );
        
        return OrderOutput::fromEntity($order);
    }
}
```

## Collection Operations with Filters

Handle collection operations with query parameters:

```php
#[ApiResource(operations: [
    new GetCollection(
        uriTemplate: '/companies/{companyId}/products',
        provider: ListProductsAction::class,
    ),
])]
final class ProductResource {}

final readonly class ListProductsAction
{
    public function __construct(
        private ProductRepository $repository,
    ) {}

    public function __invoke(
        CompanyId $companyId,    // Auto-mapped from URI
        Request $request,         // To access query parameters
    ): array {
        // Get filters from query parameters
        $category = $request->query->get('category');
        $minPrice = $request->query->get('minPrice');
        $maxPrice = $request->query->get('maxPrice');
        $limit = (int) $request->query->get('limit', 50);
        $offset = (int) $request->query->get('offset', 0);
        
        $products = $this->repository->findByCompanyWithFilters(
            $companyId,
            $category,
            $minPrice ? (float) $minPrice : null,
            $maxPrice ? (float) $maxPrice : null,
            $limit,
            $offset
        );
        
        return array_map(
            fn(Product $product) => ProductOutput::fromEntity($product),
            $products
        );
    }
}
```

## Subresource Operations

Handle subresource operations with proper typing:

```php
#[ApiResource(operations: [
    new Post(
        uriTemplate: '/orders/{orderId}/items',
        uriVariables: [
            'orderId' => new Link(fromClass: OrderResource::class),
        ],
        input: AddOrderItemInput::class,
        processor: AddOrderItemAction::class,
    ),
])]
final class OrderItemResource {}

final readonly class AddOrderItemAction
{
    public function __construct(
        private OrderRepository $orderRepository,
        private ProductRepository $productRepository,
    ) {}

    public function __invoke(
        AddOrderItemInput $input,
        OrderId $orderId,         // Auto-mapped from {orderId}
    ): OrderItemOutput {
        $order = $this->orderRepository->find($orderId);
        if (!$order) {
            throw new NotFoundHttpException('Order not found');
        }
        
        $product = $this->productRepository->find(
            ProductId::fromString($input->productId)
        );
        if (!$product) {
            throw new NotFoundHttpException('Product not found');
        }
        
        $orderItem = $order->addItem(
            product: $product,
            quantity: $input->quantity
        );
        
        $this->orderRepository->save($order);
        
        return OrderItemOutput::fromEntity($orderItem);
    }
}
```

## Error Handling

The bundle handles various error scenarios:

```php
final readonly class SecureAction
{
    public function __invoke(
        ResourceInput $data,
        CompanyId $companyId,     // Throws 404 if not found in URI
        ?UserInterface $user,      // Null if not authenticated
    ): ResourceOutput {
        // Handle authentication
        if (!$user) {
            throw new UnauthorizedException('Authentication required');
        }
        
        // Value object constructor can throw validation errors
        try {
            $email = Email::fromString($data->email);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException('Invalid email format');
        }
        
        // Business logic...
        return new ResourceOutput(/* ... */);
    }
}
```

## Custom Value Objects with Validation

Create rich value objects that validate on construction:

```php
final readonly class Email
{
    public function __construct(public string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }
    }
    
    public static function fromString(string $email): self
    {
        return new self(strtolower(trim($email)));
    }
    
    public function getDomain(): string
    {
        return substr($this->value, strpos($this->value, '@') + 1);
    }
    
    public function toString(): string
    {
        return $this->value;
    }
}

final readonly class PhoneNumber
{
    private function __construct(
        public string $countryCode,
        public string $number,
    ) {}
    
    public static function fromString(string $phone): self
    {
        // Parse international format
        if (!preg_match('/^\+(\d{1,3})-(\d{9,15})$/', $phone, $matches)) {
            throw new \InvalidArgumentException('Invalid phone format. Use: +XX-XXXXXXXXX');
        }
        
        return new self($matches[1], $matches[2]);
    }
    
    public function toString(): string
    {
        return "+{$this->countryCode}-{$this->number}";
    }
}
```

## Testing Invokable Processors

Example of testing your invokable processors:

```php
use PHPUnit\Framework\TestCase;

class CreateUserActionTest extends TestCase
{
    private CreateUserAction $action;
    private UserRepository $repository;
    
    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserRepository::class);
        $this->action = new CreateUserAction($this->repository);
    }
    
    public function testCreatesUserSuccessfully(): void
    {
        // Arrange
        $input = new CreateUserInput(
            email: 'test@example.com',
            name: 'Test User'
        );
        $companyId = new CompanyId('550e8400-e29b-41d4-a716-446655440000');
        
        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(User::class));
        
        // Act
        $result = ($this->action)($input, $companyId);
        
        // Assert
        $this->assertInstanceOf(UserOutput::class, $result);
        $this->assertEquals('test@example.com', $result->email);
        $this->assertEquals('Test User', $result->name);
    }
}
```

## Benefits Summary

Using the ApiPlatformInvokerBundle provides:

1. **Type Safety** - Full typing for URI variables instead of array access
2. **Clean Code** - No boilerplate code for parameter extraction
3. **Auto-mapping** - URI variables automatically mapped when names match
4. **Flexibility** - Multiple construction methods for value objects
5. **IDE Support** - Full autocomplete and type hints
6. **Testability** - Easy to unit test with proper dependency injection
7. **Symfony Integration** - Seamless integration with Symfony services

The bundle makes your API Platform code cleaner, safer, and more maintainable while preserving all of API Platform's powerful features.