# DDD Examples & Clean Architecture

This document demonstrates how to implement Domain-Driven Design (DDD) patterns and clean architecture with the ApiPlatformInvokerBundle, including complete CRUD operations.

## Architecture Overview

### Layer Separation

- **Domain Layer**: Value Objects, Entities, Domain Services, Commands
- **Application Layer**: Handlers, DTOs, Service Interfaces  
- **Infrastructure Layer**: API Actions, Repositories, Event Dispatchers

### Recommended Structure

```
src/
├── Domain/
│   ├── Product/
│   │   ├── Product.php                  # Entity
│   │   ├── ProductId.php                # Value Object
│   │   ├── Money.php                    # Value Object
│   │   ├── CreateProduct.php            # Command
│   │   └── CreatesProducts.php          # Handler Interface
├── Application/
│   ├── Product/
│   │   ├── CreateProductHandler.php     # Handler Implementation
│   │   ├── CreateProductInput.php       # Input DTO
│   │   └── ProductOutput.php            # Output DTO
└── Infrastructure/
    ├── Api/
    │   └── Product/
    │       └── CreateProductAction.php  # API Processor
    └── Persistence/
        └── DoctrineProductRepository.php # Repository Implementation
```

## Complete CRUD Example with DDD

### 1. Resource Configuration

```php
use ApiPlatform\Metadata\{ApiResource, Get, Post, Put, Delete, GetCollection, Link};

#[ApiResource(operations: [
    new GetCollection(
        uriTemplate: '/companies/{companyId}/products',
        uriVariables: ['companyId' => new Link(fromClass: CompanyResource::class)],
        output: ProductOutput::class,
        provider: ListCompanyProductsAction::class,
    ),
    new Get(
        uriTemplate: '/companies/{companyId}/products/{productId}',
        uriVariables: [
            'companyId' => new Link(fromClass: CompanyResource::class),
            'productId' => new Link(fromClass: ProductResource::class),
        ],
        output: ProductOutput::class,
        provider: GetProductAction::class,
    ),
    new Post(
        uriTemplate: '/companies/{companyId}/products',
        uriVariables: ['companyId' => new Link(fromClass: CompanyResource::class)],
        input: CreateProductInput::class,
        output: ProductOutput::class,
        processor: CreateProductAction::class,
    ),
    new Put(
        uriTemplate: '/companies/{companyId}/products/{productId}',
        uriVariables: [
            'companyId' => new Link(fromClass: CompanyResource::class),
            'productId' => new Link(fromClass: ProductResource::class),
        ],
        input: UpdateProductInput::class,
        output: ProductOutput::class,
        processor: UpdateProductAction::class,
    ),
    new Delete(
        uriTemplate: '/companies/{companyId}/products/{productId}',
        uriVariables: [
            'companyId' => new Link(fromClass: CompanyResource::class),
            'productId' => new Link(fromClass: ProductResource::class),
        ],
        processor: DeleteProductAction::class,
    ),
])]
final class ProductResource {}
```

### 2. Domain Layer - Value Objects

```php
// Domain Value Objects
final readonly class ProductId
{
    public function __construct(public string $value)
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value)) {
            throw new \InvalidArgumentException('Invalid product ID format');
        }
    }

    public static function generate(): self
    {
        return new self(\Ramsey\Uuid\Uuid::uuid4()->toString());
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(ProductId $other): bool
    {
        return $this->value === $other->value;
    }
}

final readonly class CompanyId
{
    public function __construct(public string $value)
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value)) {
            throw new \InvalidArgumentException('Invalid company ID format');
        }
    }

    public function toString(): string
    {
        return $this->value;
    }
}

final readonly class Money
{
    public function __construct(
        public int $amount, // Store as cents to avoid float precision issues
        public Currency $currency,
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative');
        }
    }

    public static function fromFloat(float $amount, Currency $currency = Currency::USD): self
    {
        return new self((int) round($amount * 100), $currency);
    }

    public static function zero(Currency $currency = Currency::USD): self
    {
        return new self(0, $currency);
    }

    public function toFloat(): float
    {
        return $this->amount / 100;
    }

    public function add(Money $other): self
    {
        if (!$this->currency->equals($other->currency)) {
            throw new \InvalidArgumentException('Cannot add different currencies');
        }
        
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function isGreaterThan(Money $other): bool
    {
        return $this->amount > $other->amount;
    }
}

enum Currency: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';

    public function equals(Currency $other): bool
    {
        return $this->value === $other->value;
    }
}

final readonly class ProductSku
{
    private function __construct(
        public string $value,
        public string $category,
        public int $sequence,
    ) {
        if (!preg_match('/^[A-Z]{2,4}-\d{4,6}$/', $value)) {
            throw new \InvalidArgumentException('Invalid SKU format');
        }
    }

    public static function fromString(string $sku): self
    {
        [$category, $sequence] = explode('-', $sku);
        return new self($sku, $category, (int) $sequence);
    }

    public static function generate(string $category): self
    {
        $sequence = random_int(1000, 999999);
        $sku = strtoupper($category) . '-' . $sequence;
        return new self($sku, strtoupper($category), $sequence);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
```

### 3. Domain Layer - Entity

```php
final class Product
{
    private function __construct(
        private ProductId $id,
        private CompanyId $companyId,
        private string $name,
        private string $description,
        private Money $price,
        private int $stock,
        private ?ProductSku $sku,
        private ?string $category,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        ProductId $id,
        CompanyId $companyId,
        string $name,
        string $description,
        Money $price,
        int $initialStock,
        ?string $category = null,
    ): self {
        return new self(
            id: $id,
            companyId: $companyId,
            name: trim($name),
            description: trim($description),
            price: $price,
            stock: $initialStock,
            sku: $category ? ProductSku::generate($category) : null,
            category: $category ? trim($category) : null,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
    }

    public function updateDetails(string $name, string $description, Money $price): void
    {
        $this->name = trim($name);
        $this->description = trim($description);
        $this->price = $price;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updateStock(int $quantity): void
    {
        if ($quantity < 0) {
            throw new \DomainException('Stock cannot be negative');
        }
        $this->stock = $quantity;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function adjustStock(int $adjustment): void
    {
        $newStock = $this->stock + $adjustment;
        if ($newStock < 0) {
            throw new \DomainException('Insufficient stock');
        }
        $this->stock = $newStock;
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Getters
    public function getId(): ProductId { return $this->id; }
    public function getCompanyId(): CompanyId { return $this->companyId; }
    public function getName(): string { return $this->name; }
    public function getDescription(): string { return $this->description; }
    public function getPrice(): Money { return $this->price; }
    public function getStock(): int { return $this->stock; }
    public function getSku(): ?ProductSku { return $this->sku; }
    public function getCategory(): ?string { return $this->category; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
```

### 4. Domain Layer - Commands & Queries

```php
// Commands (Write Operations)
final readonly class CreateProduct
{
    public function __construct(
        public CompanyId $companyId,
        public string $name,
        public string $description,
        public Money $price,
        public int $initialStock,
        public ?string $category = null,
    ) {}
}

final readonly class UpdateProduct
{
    public function __construct(
        public ProductId $productId,
        public CompanyId $companyId,
        public string $name,
        public string $description,
        public Money $price,
        public ?string $category = null,
    ) {}
}

final readonly class DeleteProduct
{
    public function __construct(
        public ProductId $productId,
        public CompanyId $companyId,
    ) {}
}

// Queries (Read Operations)
final readonly class GetProduct
{
    public function __construct(
        public ProductId $productId,
        public CompanyId $companyId,
    ) {}
}

final readonly class ListCompanyProducts
{
    public function __construct(
        public CompanyId $companyId,
        public ?string $category = null,
        public int $limit = 50,
        public int $offset = 0,
    ) {}
}

// Handler Interfaces
interface CreatesProducts
{
    public function __invoke(CreateProduct $command): ProductId;
}

interface UpdatesProducts
{
    public function __invoke(UpdateProduct $command): void;
}

interface DeletesProducts
{
    public function __invoke(DeleteProduct $command): void;
}

interface GetsProducts
{
    public function __invoke(GetProduct $query): Product;
}

interface ListsProducts
{
    /** @return Product[] */
    public function __invoke(ListCompanyProducts $query): array;
}
```

### 5. Application Layer - DTOs

```php
// Input DTOs
final readonly class CreateProductInput
{
    public function __construct(
        public string $name,
        public string $description,
        public float $price,
        public int $initialStock,
        public ?string $category = null,
    ) {}
}

final readonly class UpdateProductInput
{
    public function __construct(
        public string $name,
        public string $description,
        public float $price,
        public ?string $category = null,
    ) {}
}

// Output DTO
final readonly class ProductOutput
{
    public function __construct(
        public string $id,
        public string $companyId,
        public string $name,
        public string $description,
        public float $price,
        public int $stock,
        public ?string $sku,
        public ?string $category,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {}

    public static function fromProduct(Product $product): self
    {
        return new self(
            id: $product->getId()->toString(),
            companyId: $product->getCompanyId()->toString(),
            name: $product->getName(),
            description: $product->getDescription(),
            price: $product->getPrice()->toFloat(),
            stock: $product->getStock(),
            sku: $product->getSku()?->toString(),
            category: $product->getCategory(),
            createdAt: $product->getCreatedAt(),
            updatedAt: $product->getUpdatedAt(),
        );
    }
}
```

### 6. Application Layer - Handlers

```php
// Command Handler - Create Product
final readonly class CreateProductHandler implements CreatesProducts
{
    public function __construct(
        private ProductRepository $productRepository,
        private CompanyRepository $companyRepository,
        private DomainEventDispatcher $eventDispatcher,
    ) {}

    public function __invoke(CreateProduct $command): ProductId
    {
        // Domain validation
        $company = $this->companyRepository->findById($command->companyId);
        if (!$company) {
            throw new \DomainException('Company not found');
        }

        // Check for duplicate names within company
        if ($this->productRepository->existsByNameAndCompany($command->name, $command->companyId)) {
            throw new \DomainException('Product with this name already exists');
        }

        // Create aggregate
        $productId = ProductId::generate();
        $product = Product::create(
            id: $productId,
            companyId: $command->companyId,
            name: $command->name,
            description: $command->description,
            price: $command->price,
            initialStock: $command->initialStock,
            category: $command->category,
        );

        // Persist
        $this->productRepository->save($product);

        // Domain events
        $this->eventDispatcher->dispatch(new ProductCreated(
            $productId,
            $command->companyId,
            $command->name,
            $command->price
        ));

        return $productId;
    }
}

// Command Handler - Update Product
final readonly class UpdateProductHandler implements UpdatesProducts
{
    public function __construct(
        private ProductRepository $productRepository,
        private DomainEventDispatcher $eventDispatcher,
    ) {}

    public function __invoke(UpdateProduct $command): void
    {
        // Load aggregate
        $product = $this->productRepository->findByIdAndCompany(
            $command->productId,
            $command->companyId
        );

        if (!$product) {
            throw new \DomainException('Product not found');
        }

        // Update through aggregate methods
        $product->updateDetails(
            $command->name,
            $command->description,
            $command->price
        );

        // Persist changes
        $this->productRepository->save($product);

        // Domain events
        $this->eventDispatcher->dispatch(new ProductUpdated(
            $command->productId,
            $command->companyId
        ));
    }
}

// Command Handler - Delete Product
final readonly class DeleteProductHandler implements DeletesProducts
{
    public function __construct(
        private ProductRepository $productRepository,
        private DomainEventDispatcher $eventDispatcher,
    ) {}

    public function __invoke(DeleteProduct $command): void
    {
        $product = $this->productRepository->findByIdAndCompany(
            $command->productId,
            $command->companyId
        );

        if (!$product) {
            throw new \DomainException('Product not found');
        }

        // Business rule: Cannot delete if stock > 0
        if ($product->getStock() > 0) {
            throw new \DomainException('Cannot delete product with remaining stock');
        }

        // Remove aggregate
        $this->productRepository->remove($product);

        // Domain events
        $this->eventDispatcher->dispatch(new ProductDeleted(
            $command->productId,
            $command->companyId
        ));
    }
}

// Query Handler - Get Product
final readonly class GetProductHandler implements GetsProducts
{
    public function __construct(
        private ProductRepository $productRepository,
    ) {}

    public function __invoke(GetProduct $query): Product
    {
        $product = $this->productRepository->findByIdAndCompany(
            $query->productId,
            $query->companyId
        );

        if (!$product) {
            throw new \DomainException('Product not found');
        }

        return $product;
    }
}

// Query Handler - List Products
final readonly class ListProductsHandler implements ListsProducts
{
    public function __construct(
        private ProductRepository $productRepository,
    ) {}

    public function __invoke(ListCompanyProducts $query): array
    {
        return $this->productRepository->findByCompany(
            $query->companyId,
            $query->category,
            $query->limit,
            $query->offset
        );
    }
}
```

### 7. Infrastructure Layer - API Actions

```php
use Speto\ApiPlatformInvokerBundle\UriVar\Attribute\MapUriVar;
use Symfony\Component\HttpFoundation\Request;

// Create Product Action
final readonly class CreateProductAction
{
    public function __construct(
        private CreatesProducts $handler,
        private GetsProducts $queryHandler,
    ) {}

    public function __invoke(
        CreateProductInput $input,
        #[MapUriVar] CompanyId $companyId,
    ): ProductOutput {
        $command = new CreateProduct(
            companyId: $companyId,
            name: $input->name,
            description: $input->description,
            price: Money::fromFloat($input->price),
            initialStock: $input->initialStock,
            category: $input->category,
        );

        $productId = ($this->handler)($command);

        // Fetch created product for response
        $query = new GetProduct($productId, $companyId);
        $product = ($this->queryHandler)($query);

        return ProductOutput::fromProduct($product);
    }
}

// Update Product Action
final readonly class UpdateProductAction
{
    public function __construct(
        private UpdatesProducts $handler,
        private GetsProducts $queryHandler,
    ) {}

    public function __invoke(
        UpdateProductInput $input,
        #[MapUriVar] CompanyId $companyId,
        #[MapUriVar] ProductId $productId,
    ): ProductOutput {
        $command = new UpdateProduct(
            productId: $productId,
            companyId: $companyId,
            name: $input->name,
            description: $input->description,
            price: Money::fromFloat($input->price),
            category: $input->category,
        );

        ($this->handler)($command);

        // Fetch updated product
        $query = new GetProduct($productId, $companyId);
        $product = ($this->queryHandler)($query);

        return ProductOutput::fromProduct($product);
    }
}

// Delete Product Action
final readonly class DeleteProductAction
{
    public function __construct(
        private DeletesProducts $handler,
    ) {}

    public function __invoke(
        #[MapUriVar] CompanyId $companyId,
        #[MapUriVar] ProductId $productId,
    ): null {
        $command = new DeleteProduct(
            productId: $productId,
            companyId: $companyId,
        );

        ($this->handler)($command);

        return null; // 204 No Content
    }
}

// Get Product Action
final readonly class GetProductAction
{
    public function __construct(
        private GetsProducts $handler,
    ) {}

    public function __invoke(
        #[MapUriVar] CompanyId $companyId,
        #[MapUriVar] ProductId $productId,
    ): ProductOutput {
        $query = new GetProduct(
            productId: $productId,
            companyId: $companyId,
        );

        $product = ($this->handler)($query);

        return ProductOutput::fromProduct($product);
    }
}

// List Products Action
final readonly class ListCompanyProductsAction
{
    public function __construct(
        private ListsProducts $handler,
    ) {}

    public function __invoke(
        #[MapUriVar] CompanyId $companyId,
        Request $request,
    ): array {
        $query = new ListCompanyProducts(
            companyId: $companyId,
            category: $request->query->get('category'),
            limit: (int) $request->query->get('limit', 50),
            offset: (int) $request->query->get('offset', 0),
        );

        $products = ($this->handler)($query);

        return array_map(
            fn(Product $product) => ProductOutput::fromProduct($product),
            $products
        );
    }
}
```

### 8. Infrastructure Layer - Repository

```php
// Domain Repository Interface
interface ProductRepository
{
    public function save(Product $product): void;
    public function findById(ProductId $id): ?Product;
    public function findByIdAndCompany(ProductId $id, CompanyId $companyId): ?Product;
    public function existsByNameAndCompany(string $name, CompanyId $companyId): bool;
    public function findByCompany(CompanyId $companyId, ?string $category, int $limit, int $offset): array;
    public function remove(Product $product): void;
}

// Infrastructure Implementation
final class DoctrineProductRepository implements ProductRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function save(Product $product): void
    {
        $this->entityManager->persist($product);
        $this->entityManager->flush();
    }

    public function findById(ProductId $id): ?Product
    {
        return $this->entityManager->find(Product::class, $id->toString());
    }

    public function findByIdAndCompany(ProductId $id, CompanyId $companyId): ?Product
    {
        return $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->where('p.id = :id AND p.companyId = :companyId')
            ->setParameters([
                'id' => $id->toString(),
                'companyId' => $companyId->toString(),
            ])
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function existsByNameAndCompany(string $name, CompanyId $companyId): bool
    {
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(Product::class, 'p')
            ->where('p.name = :name AND p.companyId = :companyId')
            ->setParameters([
                'name' => $name,
                'companyId' => $companyId->toString(),
            ])
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function findByCompany(CompanyId $companyId, ?string $category, int $limit, int $offset): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->where('p.companyId = :companyId')
            ->setParameter('companyId', $companyId->toString())
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($category !== null) {
            $qb->andWhere('p.category = :category')
               ->setParameter('category', $category);
        }

        return $qb->getQuery()->getResult();
    }

    public function remove(Product $product): void
    {
        $this->entityManager->remove($product);
        $this->entityManager->flush();
    }
}
```

## Advanced DDD Patterns

### Aggregate Boundaries

Respect aggregate boundaries in your processors:

```php
// Order aggregate handles its own invariants
final readonly class AddOrderItemAction
{
    public function __construct(
        private AddsOrderItems $handler,
    ) {}

    public function __invoke(
        AddOrderItemInput $input,
        #[MapUriVar] OrderId $orderId,
    ): OrderOutput {
        $command = new AddOrderItem(
            orderId: $orderId,
            productId: ProductId::fromString($input->productId),
            quantity: $input->quantity,
            unitPrice: Money::fromFloat($input->unitPrice),
        );

        $order = ($this->handler)($command);

        return OrderOutput::fromAggregate($order);
    }
}

// Domain Handler respects aggregate boundaries
final readonly class AddOrderItemHandler implements AddsOrderItems
{
    public function __construct(
        private OrderRepository $orderRepository,
        private ProductRepository $productRepository,
    ) {}

    public function __invoke(AddOrderItem $command): Order
    {
        // Load aggregate root
        $order = $this->orderRepository->findById($command->orderId);
        if (!$order) {
            throw new \DomainException('Order not found');
        }

        // Load referenced entity (different aggregate)
        $product = $this->productRepository->findById($command->productId);
        if (!$product) {
            throw new \DomainException('Product not found');
        }

        // Delegate to aggregate method - business rules encapsulated
        $order->addItem(
            productId: $command->productId,
            quantity: $command->quantity,
            unitPrice: $command->unitPrice,
            productName: $product->toOutput()->name, // Get name through output
        );

        // Persist aggregate
        $this->orderRepository->save($order);

        return $order;
    }
}
```

### Domain Services for Complex Logic

```php
// Domain Service for complex business rules
final readonly class AccountTransferService implements TransfersFunds
{
    public function __construct(
        private AccountRepository $accountRepository,
        private TransactionRepository $transactionRepository,
        private DomainEventDispatcher $eventDispatcher,
        private FraudDetectionService $fraudDetection,
    ) {}

    public function __invoke(TransferFunds $command): TransactionId
    {
        // Load aggregates
        $fromAccount = $this->accountRepository->findById($command->fromAccountId);
        $toAccount = $this->accountRepository->findById($command->toAccountId);

        if (!$fromAccount || !$toAccount) {
            throw new \DomainException('Account not found');
        }

        // Business rule validation
        if ($fromAccount->getId()->equals($toAccount->getId())) {
            throw new \DomainException('Cannot transfer to same account');
        }

        if (!$fromAccount->hasAvailableBalance($command->amount)) {
            throw new \DomainException('Insufficient funds');
        }

        // Domain service for complex validation
        if (!$this->fraudDetection->isTransferAllowed($command)) {
            throw new \DomainException('Transfer blocked by fraud detection');
        }

        // Execute transfer through aggregate methods
        $transaction = $fromAccount->transferTo(
            toAccount: $toAccount,
            amount: $command->amount,
            reference: $command->reference,
        );

        // Persist both aggregates
        $this->accountRepository->save($fromAccount);
        $this->accountRepository->save($toAccount);
        $this->transactionRepository->save($transaction);

        // Domain events
        $this->eventDispatcher->dispatch(new FundsTransferred($transaction->getId()));

        return $transaction->getId();
    }
}
```

### Event-Driven Architecture

```php
// Domain Event
final readonly class OrderPlaced
{
    public function __construct(
        public OrderId $orderId,
        public CustomerId $customerId,
        public Money $totalAmount,
        public \DateTimeImmutable $placedAt,
    ) {}
}

// Domain Handler dispatches events
final readonly class PlaceOrderHandler implements PlacesOrders
{
    public function __construct(
        private OrderRepository $orderRepository,
        private DomainEventDispatcher $eventDispatcher,
    ) {}

    public function __invoke(PlaceOrder $command): OrderId
    {
        // Domain logic...
        $order = Order::place(/* ... */);
        
        // Persist aggregate
        $this->orderRepository->save($order);

        // Dispatch domain events only
        $this->eventDispatcher->dispatch(new OrderPlaced(
            orderId: $order->getId(),
            customerId: $order->getCustomerId(),
            totalAmount: $order->getTotalAmount(),
            placedAt: $order->getPlacedAt(),
        ));

        return $order->getId();
    }
}

// Infrastructure subscriber handles side effects
final readonly class OrderEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private MailerInterface $mailer,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            OrderPlaced::class => 'onOrderPlaced',
        ];
    }

    public function onOrderPlaced(OrderPlaced $event): void
    {
        // Send async notification
        $this->messageBus->dispatch(new SendOrderNotification(
            $event->orderId->toString()
        ));

        // Update analytics
        $this->messageBus->dispatch(new UpdateOrderAnalytics(
            $event->orderId->toString(),
            $event->totalAmount->toFloat()
        ));
    }
}
```

## Benefits of DDD with ApiPlatformInvokerBundle

### 1. **Clear Separation of Concerns**
- API layer only handles HTTP concerns
- Business logic isolated in domain handlers
- Infrastructure details abstracted away

### 2. **Type Safety**
- Value objects prevent primitive obsession
- Commands and queries are strongly typed
- IDE autocomplete and static analysis

### 3. **Testability**
- Domain handlers can be unit tested independently
- API actions are thin and easy to test
- Mock repositories and services easily

### 4. **Maintainability**
- Changes to business logic don't affect API layer
- Clear dependency direction (Infrastructure → Application → Domain)
- Easy to add new operations

### 5. **Domain Focus**
- Business rules are explicit and centralized
- Domain language is preserved in code
- Complex business logic is properly encapsulated

### 6. **Scalability**
- CQRS allows separate read and write models
- Event-driven architecture for loose coupling
- Easy to add new bounded contexts

## Testing Strategy

### Unit Testing Domain Handlers

```php
class CreateProductHandlerTest extends TestCase
{
    public function test_creates_product_with_valid_command(): void
    {
        $productRepository = $this->createMock(ProductRepository::class);
        $companyRepository = $this->createMock(CompanyRepository::class);
        $eventDispatcher = $this->createMock(DomainEventDispatcher::class);
        
        $company = Company::create(CompanyId::generate(), 'Test Company');
        $companyRepository->method('findById')->willReturn($company);
        
        $productRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Product::class));
        
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ProductCreated::class));
        
        $handler = new CreateProductHandler(
            $productRepository,
            $companyRepository,
            $eventDispatcher
        );
        
        $command = new CreateProduct(
            companyId: $company->getId(),
            name: 'Test Product',
            description: 'A test product',
            price: Money::fromFloat(99.99),
            initialStock: 100,
            category: 'Electronics'
        );
        
        $productId = $handler($command);
        
        $this->assertInstanceOf(ProductId::class, $productId);
    }
}
```

### Integration Testing API Actions

```php
class CreateProductActionTest extends KernelTestCase
{
    public function test_creates_product_via_api_action(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        
        $action = $container->get(CreateProductAction::class);
        
        $input = new CreateProductInput(
            name: 'Test Product',
            description: 'Description',
            price: 99.99,
            initialStock: 10,
            category: 'Electronics'
        );
        
        $companyId = new CompanyId('550e8400-e29b-41d4-a716-446655440000');
        
        $output = $action($input, $companyId);
        
        $this->assertInstanceOf(ProductOutput::class, $output);
        $this->assertEquals('Test Product', $output->name);
        $this->assertEquals(99.99, $output->price);
    }
}
```

## Simple CQRS Pattern

When you need to separate command (write) and query (read) responsibilities, CQRS provides a clean solution without overengineering. The ApiPlatformInvokerBundle makes this pattern easy to implement with typed invokable processors:

### API Resource Configuration

```php
use ApiPlatform\Metadata\{ApiResource, Get, Post, Put, Delete, GetCollection};

#[ApiResource(operations: [
    new GetCollection(
        uriTemplate: '/companies/{companyId}/products',
        output: ProductListView::class,
        provider: ListProductsAction::class, // ✨ Invokable provider
    ),
    new Get(
        uriTemplate: '/companies/{companyId}/products/{productId}',
        output: ProductDetailView::class,
        provider: GetProductAction::class,   // ✨ Invokable provider
    ),
    new Post(
        uriTemplate: '/companies/{companyId}/products',
        input: CreateProductInput::class,
        output: ProductOutput::class,
        processor: CreateProductAction::class, // ✨ Invokable processor
    ),
    new Put(
        uriTemplate: '/companies/{companyId}/products/{productId}',
        input: UpdateProductInput::class,
        output: ProductOutput::class,
        processor: UpdateProductAction::class, // ✨ Invokable processor
    ),
    new Delete(
        uriTemplate: '/companies/{companyId}/products/{productId}',
        processor: DeleteProductAction::class, // ✨ Invokable processor
    ),
])]
final class ProductResource {}
```

### How the Bundle Enables Clean CQRS

Without the bundle, you'd need to implement ProcessorInterface:

```php
// ❌ Traditional API Platform - Boilerplate and no type safety
class CreateProductProcessor implements ProcessorInterface
{
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): mixed {
        $companyId = $uriVariables['companyId'] ?? null; // Manual extraction
        // ... lots of boilerplate
    }
}
```

With the bundle, you get clean invokable Actions:

```php
// ✅ With ApiPlatformInvokerBundle - Clean and type-safe
final readonly class CreateProductAction
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
    ) {}

    public function __invoke(
        CreateProductInput $input,    // ✨ Typed input from request body
        CompanyId $companyId,          // ✨ Auto-mapped from {companyId} URI variable
    ): ProductOutput {                 // ✨ Typed output
        // Execute command
        $productId = $this->commandBus->dispatch(
            new CreateProductCommand(
                companyId: $companyId,
                name: $input->name,
                description: $input->description,
                price: $input->price,
                initialStock: $input->stock,
            )
        );

        // Query for response
        $view = $this->queryBus->dispatch(
            new GetProductQuery($productId, $companyId)
        );

        return ProductOutput::fromView($view);
    }
}
```

### CQRS Implementation

```php
// Commands for write operations
final readonly class CreateProductCommand
{
    public function __construct(
        public CompanyId $companyId,
        public string $name,
        public string $description,
        public float $price,
        public int $initialStock,
    ) {}
}

// Command Handler - Business logic for writes
final readonly class CreateProductHandler
{
    public function __construct(
        private ProductRepository $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function handle(CreateProductCommand $command): ProductId
    {
        // Rich domain entity with business logic
        $product = Product::create(
            id: ProductId::generate(),
            companyId: $command->companyId,
            name: $command->name,
            description: $command->description,
            price: Money::fromFloat($command->price),
            initialStock: $command->initialStock,
        );

        // Save through repository
        $this->repository->save($product);

        // Dispatch domain event
        $this->eventDispatcher->dispatch(new ProductCreated(
            $product->getId(),
            $command->companyId
        ));

        return $product->getId();
    }
}

// Query for read operations
final readonly class GetProductQuery
{
    public function __construct(
        public ProductId $productId,
        public CompanyId $companyId,
    ) {}
}

// Query Handler - Optimized reads
final readonly class GetProductHandler
{
    public function __construct(
        private ProductRepository $repository,
        private CacheInterface $cache,
    ) {}

    public function handle(GetProductQuery $query): ProductDetailView
    {
        // Check cache first
        $cacheKey = "product_{$query->productId->toString()}";
        
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }

        // Load entity
        $product = $this->repository->findByIdAndCompany(
            $query->productId,
            $query->companyId
        );

        if (!$product) {
            throw new ProductNotFoundException($query->productId);
        }

        // Transform to read-optimized view
        $view = ProductDetailView::fromEntity($product);
        
        // Cache for performance
        $this->cache->set($cacheKey, $view, 3600);
        
        return $view;
    }
}

// Read-optimized view model
final readonly class ProductDetailView
{
    public function __construct(
        public string $id,
        public string $companyId,
        public string $name,
        public string $description,
        public float $price,
        public string $currency,
        public int $stock,
        public bool $isAvailable,
        public ?string $sku,
        public ?string $category,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {}

    public static function fromEntity(Product $product): self
    {
        $output = $product->toOutput();
        
        return new self(
            id: $output->id,
            companyId: $output->companyId,
            name: $output->name,
            description: $output->description,
            price: $output->price,
            currency: $output->currency,
            stock: $output->stock,
            isAvailable: $output->isAvailable,
            sku: $output->sku,
            category: $output->category,
            createdAt: $output->createdAt,
            updatedAt: $output->updatedAt,
        );
    }
}
```

### API Actions Leveraging the Bundle

```php
// Read operation (Provider) - Automatic URI mapping
final readonly class GetProductAction
{
    public function __construct(
        private QueryBus $queryBus,
    ) {}

    public function __invoke(
        CompanyId $companyId,    // ✨ Auto-mapped from {companyId}
        ProductId $productId,     // ✨ Auto-mapped from {productId}
    ): ProductDetailView {        // ✨ Typed output
        return $this->queryBus->dispatch(
            new GetProductQuery($productId, $companyId)
        );
    }
}

// List operation - Combines URI variables with query params
final readonly class ListProductsAction
{
    public function __construct(
        private QueryBus $queryBus,
    ) {}

    public function __invoke(
        CompanyId $companyId,     // ✨ Auto-mapped from URI
        Request $request,          // ✨ For query parameters
    ): array {
        $products = $this->queryBus->dispatch(
            new ListProductsQuery(
                companyId: $companyId->toString(),
                limit: (int) $request->query->get('limit', 50),
                offset: (int) $request->query->get('offset', 0),
            )
        );

        return $products; // Array of ProductListView
    }
}

// Update operation - Combines input, URI variables
final readonly class UpdateProductAction
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
    ) {}

    public function __invoke(
        UpdateProductInput $input,  // ✨ Typed input from request body
        CompanyId $companyId,        // ✨ Auto-mapped from {companyId}
        ProductId $productId,        // ✨ Auto-mapped from {productId}
    ): ProductOutput {
        // Execute update command
        $this->commandBus->dispatch(
            new UpdateProductCommand(
                productId: $productId,
                companyId: $companyId,
                name: $input->name,
                description: $input->description,
                price: $input->price,
            )
        );

        // Query updated product
        $view = $this->queryBus->dispatch(
            new GetProductQuery($productId, $companyId)
        );

        return ProductOutput::fromView($view);
    }
}

// Delete operation - Only URI variables
final readonly class DeleteProductAction
{
    public function __construct(
        private CommandBus $commandBus,
    ) {}

    public function __invoke(
        CompanyId $companyId,    // ✨ Auto-mapped
        ProductId $productId,     // ✨ Auto-mapped
    ): null {
        $this->commandBus->dispatch(
            new DeleteProductCommand($productId, $companyId)
        );

        return null; // 204 No Content
    }
}


// Lighter view for lists
final readonly class ProductListView
{
    public function __construct(
        public string $id,
        public string $name,
        public float $price,
        public bool $isAvailable,
    ) {}

    public static function fromEntity(Product $product): self
    {
        $output = $product->toOutput();
        
        return new self(
            id: $output->id,
            name: $output->name,
            price: $output->price,
            isAvailable: $output->isAvailable,
        );
    }
}
```

### Cache Invalidation

```php
// Event listener to invalidate cache on updates
final readonly class ProductCacheInvalidator
{
    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function onProductUpdated(ProductUpdated $event): void
    {
        $this->cache->delete("product_{$event->productId->toString()}");
    }

    public function onProductDeleted(ProductDeleted $event): void
    {
        $this->cache->delete("product_{$event->productId->toString()}");
    }
}
```

### Benefits of CQRS with ApiPlatformInvokerBundle

1. **Bundle Benefits**
   - ✅ No ProcessorInterface boilerplate
   - ✅ Automatic URI variable mapping to value objects
   - ✅ Full type safety for inputs and outputs
   - ✅ Clean invokable Actions as thin HTTP adapters

2. **CQRS Benefits**
   - ✅ Commands express business intent
   - ✅ Queries optimize for read performance
   - ✅ Different view models for different use cases
   - ✅ Clear separation of concerns

3. **Combined Power**
   - ✅ API Actions are just thin adapters calling command/query bus
   - ✅ Business logic stays in handlers, not in API layer
   - ✅ Easy to test each layer independently
   - ✅ Type safety from HTTP request to domain layer

This architecture ensures your API Platform applications remain maintainable and follow solid DDD principles while leveraging the convenience of the ApiPlatformInvokerBundle.