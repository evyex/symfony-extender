# MapEntityCollection

`MapEntityCollection` is an attribute for a Symfony controller argument that automatically builds a Doctrine query for an entity collection based on incoming parameters.

By default, the result is returned as `Doctrine\ORM\Tools\Pagination\Paginator`, but you can also return a plain array.

## Basic Usage

```php
use App\Entity\Product;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Evyex\SymfonyExtender\ValueResolver\MapEntityCollection\MapEntityCollection;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/products', methods: ['GET'])]
public function list(
    #[MapEntityCollection(
        class: Product::class,
        defaultOrdering: ['createdAt' => MapEntityCollection::ORDERING_DESC],
        fetchAssociation: ['category', 'images'],
    )]
    Paginator $products,
): Response {
    // ...
}
```

## Example with Query DTO

```php
use App\Entity\Product;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Evyex\SymfonyExtender\ValueResolver\MapEntityCollection\MapEntityCollection;
use Evyex\SymfonyExtender\ValueResolver\MapEntityCollection\MappingType;

final class ProductListQuery
{
    public ?string $status = null;
    public ?int $limit = 20;
    public ?int $page = 1;
}

#[Route('/products', methods: ['GET'])]
public function list(
    ProductListQuery $query,
    #[MapEntityCollection(
        class: Product::class,
        queryObject: 'query',
        queryMapping: [
            'limit' => MappingType::LIMIT,
            'page' => MappingType::PAGE,
        ],
    )]
    Paginator $products,
): Response {
    // status will be mapped to where ecr.status = :status
    // limit/page will be used for pagination
}
```

## Attribute Parameters

### `class`

`class-string` of the target Doctrine entity for which the `QueryBuilder` is created.

### `queryObject`

The name of a controller argument (usually a DTO) whose properties are used for filtering and pagination.

If `null`, DTO properties are not processed.

### `queryMapping`

`array<string, string>` with rules for DTO property handling.

- `MappingType::IGNORE` - ignore the property.
- `MappingType::LIMIT` - sets `setMaxResults(...)` (default: `entity_collection.default_limit`).
- `MappingType::OFFSET` - sets `setFirstResult(...)` directly; also triggers pagination so `LIMIT` defaults apply even without `PAGE`.
- `MappingType::PAGE` - together with `LIMIT`, computes offset as `(page - 1) * limit`; defaults to page `1` when not provided.
- if the key exists but the value is not a special mapping type, it behaves like a regular filter field.
- if the key does not exist, the property is treated as an entity field and a `=`/`IN` condition is added.

Pagination (`setMaxResults` / `setFirstResult`) is applied whenever at least one of the following is true: `PAGE` is mapped, `OFFSET` is mapped, or `returnPaginator` is `true`.

### `doctrineParameters`

`array<string, mixed>` for predefined query conditions (applied before custom filters).

Supported values:

- scalar/array values (`=` or `IN`);
- `MappingType::NULL` (`IS NULL`);
- `MappingType::NOT_NULL` (`IS NOT NULL`);
- a string key of a request attribute (value is taken from `$request->attributes`);
- `Symfony\Component\ExpressionLanguage\Expression` (evaluated via ExpressionLanguage, `user` is available).

### `filters`

`array<class-string<DoctrineFilterInterface>>` with a list of filter services.

Each filter receives `QueryBuilder`, the current attribute, `Request`, and the object from `queryObject`, and can modify the query as needed.

### `defaultOrdering`

`array<string, 'ASC'|'DESC'>` with default sorting.

Applied only when no ordering was already added earlier (for example, by filters).

### `returnPaginator`

- `true` (default): return `Paginator`; also triggers default pagination when a `queryObject` is present but no `PAGE`/`OFFSET` mapping is defined — `LIMIT` defaults to `entity_collection.default_limit`, page defaults to `1`.
- `false`: execute the query and return an `array` of results.

### `nameConverter`

Optional `NameConverterInterface` for converting field names in `defaultOrdering` (for example, from request camelCase to entity snake_case).

### `fetchAssociation`

`string[]` containing associations that must be explicitly loaded and hydrated together with the root entity.

An item may be:

- an association property of the root entity, for example `category` or `images`; the resolver creates a `LEFT JOIN` and adds the generated join alias to `SELECT`;
- an existing join alias previously added to the query by a custom `DoctrineFilterInterface`; the resolver adds that alias to `SELECT`.

```php
#[MapEntityCollection(
    class: Product::class,
    fetchAssociation: ['category', 'images'],
)]
Paginator $products
```

This avoids additional lazy-loading queries when the related objects are accessed. Association properties are resolved relative to the root entity alias, so nested paths such as `category.parent` are not supported directly. Add the nested join in a custom filter and pass its alias to `fetchAssociation` instead.

## Notes

- `MapEntityCollection` is attached to a controller argument and is resolved internally by `EntityCollectionValueResolver`.
- Sorting direction constants:
  - `MapEntityCollection::ORDERING_ASC`
  - `MapEntityCollection::ORDERING_DESC`
- Pagination is triggered when any of the following is true: `PAGE` is mapped, `OFFSET` is mapped, or `returnPaginator` is `true` (with a `queryObject` present). In all these cases, if no explicit `LIMIT` property is mapped, the bundle's `entity_collection.default_limit` is used (default: `20`), and `page` defaults to `1`.
