# Symfony Extender Bundle

A Symfony bundle that provides commonly used features and utilities to enhance your development workflow.

## Installation

Install the bundle using Composer:

```bash
composer require evyex/symfony-extender
```

The bundle should be automatically registered by Symfony Flex. If not, add it to your `config/bundles.php`:

```php
return [
    // ...
    Evyex\SymfonyExtender\SymfonyExtenderBundle::class => ['all' => true],
];
```

## Configuration

You can configure the bundle in `config/packages/symfony_extender.yaml`:

```yaml
symfony_extender:
    entity_collection:
        default_limit: 20      # Default paginator limit when no explicit limit is provided (min: 1)
    is_granted_listener:
        enabled: true          # Set to false to disable grant execution after Map** resolving
```

## Features

### 1. Phone Number Validator

A simple validator for international phone numbers. It allows digits, spaces, hyphens, and parentheses, but ensures the underlying value follows a valid international format (e.g., `+1234567890`).

**Usage:**

```php
use Evyex\SymfonyExtender\Validator\PhoneNumber;

class UserDTO
{
    #[PhoneNumber(message: 'Please provide a valid phone number.')]
    public string $phone;
}
```

### 2. MapEntityCollection Value Resolver

Automatically resolves a collection of entities from request query parameters. This is highly useful for list endpoints with filtering, ordering, and pagination support.

Detailed documentation: [`MapEntityCollection.md`](src/ValueResolver/MapEntityCollection/MapEntityCollection.md).

**Usage in Controller:**

```php
use Evyex\SymfonyExtender\ValueResolver\MapEntityCollection\MapEntityCollection;
use App\Entity\Product;
use Doctrine\ORM\Tools\Pagination\Paginator;

#[Route('/products', methods: ['GET'])]
public function list(
    #[MapEntityCollection(
        class: Product::class,
        defaultOrdering: ['createdAt' => 'DESC']
    )]
    Paginator $products
): Response {
    // ...
}
```

### 3. IsGranted Attribute Decorator

Decorates the default Symfony `controller.is_granted_attribute_listener` to ensure it runs at the correct priority when used with other argument resolvers.

This works transparently in the background, ensuring that `#[IsGranted]` attributes on controller arguments are handled correctly before the value resolvers are called.

Can be disabled via configuration if the decorator conflicts with your setup (see [Configuration](#configuration)).

## Quality Assurance

The project maintains high code quality standards:

- **Static Analysis**: PHPStan
- **Coding Style**: PHP-CS-Fixer
- **Testing**: PHPUnit

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
