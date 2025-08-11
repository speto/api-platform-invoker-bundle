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

## Basic Usage

### Simple Invokable Processor

Instead of implementing `ProcessorInterface`, you can use a simple callable class:

```php
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Speto\ApiPlatformInvokerBundle\UriVar\Attribute\MapUriVar;

#[ApiResource(operations: [
    new Post(
        uriTemplate: '/companies/{companyId}/users',
        processor: RegisterUserAction::class,
    ),
])]
final class UserResource {}

// Simple invokable processor - no ProcessorInterface needed!
final readonly class RegisterUserAction
{
    public function __construct(
        private UserRepository $userRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function __invoke(
        UserResource $data,
        #[MapUriVar('companyId')] CompanyId $companyId,
    ): UserResource {
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

## Advanced Examples

### Multiple URI Variables

Handle complex routes with multiple typed parameters:

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
        #[MapUriVar('departmentId')] DepartmentId $departmentId,
        #[MapUriVar('teamId')] TeamId $teamId,
        #[MapUriVar('memberId')] MemberId $memberId,
    ): TeamMemberResource {
        $member = $this->repository->findByIds($departmentId, $teamId, $memberId);
        
        if (!$member) {
            throw new NotFoundHttpException('Team member not found');
        }
        
        return TeamMemberResource::fromEntity($member);
    }
}
```

### Value Objects with Custom Constructors

The bundle supports multiple ways to construct value objects from URI variables:

```php
use Speto\ApiPlatformInvokerBundle\UriVar\Attribute\UriVarConstructor;

// Option 1: Regular constructor
final readonly class UserId
{
    public function __construct(public string $value)
    {
        if (!uuid_is_valid($value)) {
            throw new \InvalidArgumentException('Invalid UUID');
        }
    }
}

// Option 2: Static factory method (automatically detected)
final readonly class ProductSku
{
    private function __construct(public string $value) {}
    
    public static function fromString(string $value): self
    {
        // Validation logic here
        return new self(strtoupper($value));
    }
}

// Option 3: Explicit constructor method via attribute
#[UriVarConstructor('fromUrl')]
final readonly class OrderNumber
{
    private function __construct(public string $value) {}
    
    public static function fromUrl(string $value): self
    {
        if (!preg_match('/^ORD-\d{6}$/', $value)) {
            throw new \InvalidArgumentException('Invalid order number format');
        }
        return new self($value);
    }
    
    public static function fromCsvLine(string $value): self
    {
        $fields = str_getcsv($value);
        // Assume order number is in the 4th column
        if (!isset($fields[0]) || !preg_match('/^ORD-\d{6}$/', $fields[3])) {
            throw new \InvalidArgumentException('Invalid order number format in CSV');
        }
        return new self($fields[3]);
    }
    
    public static function generate(): self
    {
        return new self('ORD-' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT));
    }
}
```

### Complete CRUD Example

Here's a full example with Create, Read, Update, and Delete operations:

```php
use ApiPlatform\Metadata\{ApiResource, Get, Post, Put, Delete, GetCollection};
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[ApiResource(operations: [
    new GetCollection(
        uriTemplate: '/products',
        processor: ListProductsAction::class,
    ),
    new Get(
        uriTemplate: '/products/{productId}',
        processor: GetProductAction::class,
    ),
    new Post(
        uriTemplate: '/products',
        processor: CreateProductAction::class,
    ),
    new Put(
        uriTemplate: '/products/{productId}',
        processor: UpdateProductAction::class,
    ),
    new Delete(
        uriTemplate: '/products/{productId}',
        processor: DeleteProductAction::class,
    ),
])]
final class ProductResource
{
    public ?string $id = null;
    public string $name;
    public string $description;
    public float $price;
    public int $stock;
}

// List all products
final readonly class ListProductsAction
{
    public function __construct(
        private ProductRepository $repository,
    ) {}

    public function __invoke(): array
    {
        $products = $this->repository->findAll();
        
        return array_map(
            fn(Product $product) => $this->toResource($product),
            $products
        );
    }
    
    private function toResource(Product $product): ProductResource
    {
        $resource = new ProductResource();
        $resource->id = $product->getId()->toString();
        $resource->name = $product->getName();
        $resource->description = $product->getDescription();
        $resource->price = $product->getPrice();
        $resource->stock = $product->getStock();
        
        return $resource;
    }
}

// Get single product
final readonly class GetProductAction
{
    public function __construct(
        private ProductRepository $repository,
    ) {}

    public function __invoke(
        #[MapUriVar('productId')] ProductId $productId,
    ): ProductResource {
        $product = $this->repository->find($productId);
        
        if (!$product) {
            throw new NotFoundHttpException('Product not found');
        }
        
        return $this->toResource($product);
    }
    
    private function toResource(Product $product): ProductResource
    {
        $resource = new ProductResource();
        $resource->id = $product->getId()->toString();
        $resource->name = $product->getName();
        $resource->description = $product->getDescription();
        $resource->price = $product->getPrice();
        $resource->stock = $product->getStock();
        
        return $resource;
    }
}

// Create new product
final readonly class CreateProductAction
{
    public function __construct(
        private ProductRepository $repository,
        private ValidatorInterface $validator,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function __invoke(
        ProductResource $data,
    ): ProductResource {
        // Validate input
        $violations = $this->validator->validate($data);
        if (count($violations) > 0) {
            throw new ValidationException($violations);
        }
        
        // Create entity
        $product = new Product(
            id: ProductId::generate(),
            name: $data->name,
            description: $data->description,
            price: $data->price,
            stock: $data->stock,
        );
        
        // Save and dispatch event
        $this->repository->save($product);
        $this->eventDispatcher->dispatch(new ProductCreated($product->getId()));
        
        // Return resource with generated ID
        $data->id = $product->getId()->toString();
        return $data;
    }
}

// Update existing product
final readonly class UpdateProductAction
{
    public function __construct(
        private ProductRepository $repository,
        private ValidatorInterface $validator,
    ) {}

    public function __invoke(
        ProductResource $data,
        #[MapUriVar('productId')] ProductId $productId,
    ): ProductResource {
        // Find existing product
        $product = $this->repository->find($productId);
        if (!$product) {
            throw new NotFoundHttpException('Product not found');
        }
        
        // Validate input
        $violations = $this->validator->validate($data);
        if (count($violations) > 0) {
            throw new ValidationException($violations);
        }
        
        // Update entity
        $product->updateDetails(
            name: $data->name,
            description: $data->description,
            price: $data->price,
        );
        $product->updateStock($data->stock);
        
        // Save changes
        $this->repository->save($product);
        
        // Return updated resource
        $data->id = $product->getId()->toString();
        return $data;
    }
}

// Delete product
final readonly class DeleteProductAction
{
    public function __construct(
        private ProductRepository $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function __invoke(
        #[MapUriVar('productId')] ProductId $productId,
    ): null {
        $product = $this->repository->find($productId);
        
        if (!$product) {
            throw new NotFoundHttpException('Product not found');
        }
        
        // Delete and dispatch event
        $this->repository->remove($product);
        $this->eventDispatcher->dispatch(new ProductDeleted($productId));
        
        // Return null for 204 No Content response
        return null;
    }
}
```

### Using Symfony Services

Leverage Symfony's ArgumentResolver to inject any service or request data:

```php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Psr\Log\LoggerInterface;

final readonly class ComplexAction
{
    public function __construct(
        private Security $security,
        private LoggerInterface $logger,
        private MailerInterface $mailer,
    ) {}

    public function __invoke(
        OrderResource $data,
        Request $request,  // Automatically injected
        #[MapUriVar('shopId')] ShopId $shopId,
        #[MapUriVar('customerId')] CustomerId $customerId,
    ): OrderResource {
        // Access current user
        $user = $this->security->getUser();
        
        // Log the action
        $this->logger->info('Processing order', [
            'shop_id' => $shopId->toString(),
            'customer_id' => $customerId->toString(),
            'user' => $user?->getUserIdentifier(),
            'ip' => $request->getClientIp(),
        ]);
        
        // Process the order...
        $order = $this->processOrder($data, $shopId, $customerId);
        
        // Send confirmation email
        $this->mailer->send(
            $this->createOrderConfirmationEmail($order, $customerId)
        );
        
        return OrderResource::fromEntity($order);
    }
}
```

### Error Handling

Handle validation and business logic errors gracefully:

```php
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, ConflictHttpException};
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class RegisterAccountAction
{
    public function __construct(
        private AccountRepository $repository,
        private ValidatorInterface $validator,
        private PasswordHasherInterface $passwordHasher,
    ) {}

    public function __invoke(
        AccountRegistrationResource $data,
    ): AccountResource {
        // Validate input data
        $violations = $this->validator->validate($data);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }
            throw new BadRequestHttpException(json_encode($errors));
        }
        
        // Check for duplicate email
        if ($this->repository->findByEmail($data->email)) {
            throw new ConflictHttpException('Email already registered');
        }
        
        // Business logic validation
        if (!$this->isValidDomain($data->email)) {
            throw new BadRequestHttpException('Email domain not allowed');
        }
        
        // Create account
        $account = new Account(
            id: AccountId::generate(),
            email: $data->email,
            passwordHash: $this->passwordHasher->hash($data->password),
        );
        
        $this->repository->save($account);
        
        return AccountResource::fromEntity($account);
    }
    
    private function isValidDomain(string $email): bool
    {
        $domain = substr($email, strpos($email, '@') + 1);
        $blockedDomains = ['tempmail.com', 'throwaway.email'];
        
        return !in_array($domain, $blockedDomains, true);
    }
}
```

## How It Works

1. **Processor Detection**: The bundle decorates API Platform's state processor to detect invokable processors
2. **Argument Resolution**: When an invokable processor is detected, it uses Symfony's ArgumentResolver to resolve all parameters
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