# Architecture and Internals

This document explains how the ApiPlatformInvokerBundle works internally and its architectural decisions.

## Overview

The bundle extends API Platform's processor system by decorating the state processor and using Symfony's ArgumentResolver to enable invokable processors with typed URI variable mapping.

## Core Components

### 1. InvokableProcessorDecorator

**Location:** `src/Processor/InvokableProcessorDecorator.php`

The decorator intercepts all API Platform processor calls and determines if they should be handled as invokable processors:

```php
final readonly class InvokableProcessorDecorator implements ProcessorInterface
{
    public function process(
        mixed $data, 
        Operation $operation, 
        array $uriVariables = [], 
        array $context = []
    ): mixed {
        // Check if processor is invokable
        if (!is_callable($processor)) {
            return $this->decorated->process($data, $operation, $uriVariables, $context);
        }
        
        // Use ActionInvoker for invokable processors
        return $this->actionInvoker->invoke($processor, $data, $operation, $uriVariables, $context);
    }
}
```

### 2. ActionInvoker

**Location:** `src/Processor/ActionInvoker.php`

The core service that uses Symfony's ArgumentResolver to resolve parameters for invokable processors:

```php
final readonly class ActionInvoker
{
    public function invoke(
        callable $processor,
        mixed $data,
        Operation $operation,
        array $uriVariables,
        array $context
    ): mixed {
        // Create pseudo-request for ArgumentResolver
        $request = $this->createRequest($data, $operation, $uriVariables, $context);
        
        // Resolve arguments using Symfony's ArgumentResolver
        $arguments = $this->argumentResolver->getArguments($request, $processor);
        
        // Invoke the processor with resolved arguments
        return $processor(...$arguments);
    }
}
```

### 3. UriVarValueResolver

**Location:** `src/UriVar/UriVarValueResolver.php`

A Symfony ArgumentResolver that handles URI variable to object conversion:

```php
final readonly class UriVarValueResolver implements ValueResolverInterface
{
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        // Check for MapUriVar attribute
        $mapUriVar = $this->getMapUriVarAttribute($argument);
        if (!$mapUriVar) {
            return [];
        }
        
        // Get URI variable value
        $value = $request->attributes->get($mapUriVar->name);
        if ($value === null) {
            return [];
        }
        
        // Convert to target type
        $object = $this->uriVarInstantiator->instantiate($argument->getType(), $value);
        
        yield $object;
    }
}
```

### 4. UriVarInstantiator

**Location:** `src/UriVar/UriVarInstantiator.php`

Handles the actual conversion of string values to typed objects:

```php
final readonly class UriVarInstantiator
{
    public function instantiate(string $className, string $value): object
    {
        $reflection = new \ReflectionClass($className);
        
        // Try UriVarConstructor attribute first
        $constructorMethod = $this->getConstructorMethod($reflection);
        if ($constructorMethod) {
            return $className::{$constructorMethod}($value);
        }
        
        // Try auto-detected static factory methods
        $factoryMethod = $this->findFactoryMethod($reflection);
        if ($factoryMethod) {
            return $className::{$factoryMethod}($value);
        }
        
        // Fall back to regular constructor
        return new $className($value);
    }
}
```

## Processing Flow

1. **API Platform Request**: API Platform receives an HTTP request and determines the processor
2. **Decorator Interception**: `InvokableProcessorDecorator` intercepts the processor call
3. **Invokability Check**: Check if the processor is callable (has `__invoke` method)
4. **Request Creation**: Create a pseudo-request containing URI variables and context
5. **Argument Resolution**: Use Symfony's ArgumentResolver to resolve all method parameters
6. **URI Variable Mapping**: `UriVarValueResolver` converts URI variables to typed objects
7. **Service Injection**: Symfony injects required services via constructor or method parameters
8. **Processor Invocation**: Call the processor with resolved arguments
9. **Response**: Return the processor result to API Platform

## Service Registration

Services are registered in `src/Resources/config/services.php`:

```php
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    
    // Auto-configure all services
    $services->defaults()
        ->autowire()
        ->autoconfigure();
    
    // Register UriVarValueResolver with high priority
    $services->set(UriVarValueResolver::class)
        ->tag('controller.argument_value_resolver', ['priority' => 150]);
    
    // Decorate the API Platform state processor
    $services->set(InvokableProcessorDecorator::class)
        ->decorate('api_platform.state_processor');
};
```

## URI Variable Resolution Process

### 1. Attribute Detection

The system looks for the `#[MapUriVar]` attribute on method parameters:

```php
public function __invoke(
    #[MapUriVar('userId')] UserId $userId,  // Will be resolved
    string $regularParam,                    // Will not be resolved
): Response {
    // ...
}
```

### 2. Value Extraction

URI variables are extracted from the request attributes (populated by API Platform's routing):

```php
// For route: /users/{userId}/posts/{postId}
// Request attributes contain: ['userId' => '123', 'postId' => '456']

$userId = $request->attributes->get('userId'); // '123'
```

### 3. Type Conversion

The `UriVarInstantiator` converts string values to typed objects using various strategies:

#### Constructor Priority Order:
1. **Explicit constructor** (via `#[UriVarConstructor]` attribute)
2. **Auto-detected static factory** (`fromString`, `fromValue`, etc.)
3. **Regular constructor**

### 4. Object Creation

```php
// Example conversions:
'123' → new UserId('123')
'abc-def' → ProductSku::fromString('abc-def')
'456' → OrderNumber::fromUrl('456')
```

## Integration Points

### Symfony ArgumentResolver

The bundle integrates with Symfony's argument resolution system, allowing it to work alongside:

- Service injection
- Request/Response objects
- Security user injection
- Custom argument resolvers
- Session handling
- Form handling

### API Platform

The bundle decorates API Platform's processor system without replacing it:

- Traditional processors still work
- Resource configuration unchanged
- Full API Platform feature compatibility
- Event system integration
- Serialization/deserialization
- Validation integration

## Performance Considerations

### Minimal Overhead

- Only processes invokable processors
- Traditional processors bypass the system entirely
- URI variable resolution is lazy (only when needed)
- Reflection is cached by PHP's opcache

### Memory Efficiency

- No additional request/response objects created
- Reuses Symfony's existing ArgumentResolver infrastructure
- Value objects are created only once per request

## Extension Points

### Custom Value Resolvers

You can add custom argument resolvers that work alongside the bundle:

```php
class CustomResolver implements ValueResolverInterface
{
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        // Your custom resolution logic
        if ($argument->getType() === MyCustomType::class) {
            yield new MyCustomType($request->headers->get('Custom-Header'));
        }
    }
}
```

### Custom URI Variable Instantiation

Extend the `UriVarInstantiator` to support additional construction patterns:

```php
class ExtendedUriVarInstantiator extends UriVarInstantiator
{
    public function instantiate(string $className, string $value): object
    {
        // Add custom instantiation logic
        if ($this->supportsCustomInstantiation($className)) {
            return $this->customInstantiate($className, $value);
        }
        
        return parent::instantiate($className, $value);
    }
}
```

## Error Handling

The bundle handles various error scenarios gracefully:

### Invalid URI Variables
- Missing variables result in null values
- Type conversion errors bubble up as InvalidArgumentException
- Validation errors from value object constructors are preserved

### Processor Errors
- Non-invokable processors fall back to original behavior
- Service injection failures are handled by Symfony
- Runtime errors preserve stack traces

## Testing Strategy

The bundle includes comprehensive tests:

### Unit Tests
- Individual component testing
- Mock dependencies
- Edge case coverage

### Integration Tests
- End-to-end processor flow
- Real Symfony container
- API Platform integration
- Multiple URI variable scenarios

This architecture ensures the bundle integrates seamlessly with existing Symfony and API Platform applications while providing powerful new capabilities for processor development.