<?php

declare(strict_types=1);

namespace Evyex\SymfonyExtender\Tests\ValueResolver\MapEntityCollection;

use Doctrine\ORM\QueryBuilder;
use Evyex\SymfonyExtender\ValueResolver\MapEntityCollection\DoctrineFilterInterface;
use Evyex\SymfonyExtender\ValueResolver\MapEntityCollection\EntityCollectionValueResolver;
use Evyex\SymfonyExtender\ValueResolver\MapEntityCollection\MapEntityCollection;
use Evyex\SymfonyExtender\ValueResolver\MapEntityCollection\MappingType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * @internal
 */
#[CoversClass(MapEntityCollection::class)]
class MapEntityCollectionTest extends TestCase
{
    public function testAttributeFlags(): void
    {
        $reflection = new \ReflectionClass(MapEntityCollection::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        $this->assertCount(1, $attributes);
        $this->assertSame(
            \Attribute::TARGET_PARAMETER | \Attribute::IS_REPEATABLE,
            $attributes[0]->getArguments()[0]
        );
    }

    public function testDefaultConfiguration(): void
    {
        $attribute = new MapEntityCollection('App\Entity\Product');

        $this->assertSame(EntityCollectionValueResolver::class, $attribute->resolver);
        $this->assertFalse($attribute->disabled);
        $this->assertSame('App\Entity\Product', $attribute->getClass());
        $this->assertNull($attribute->getQueryString());
        $this->assertSame([], $attribute->getQueryMapping());
        $this->assertSame([], $attribute->getDoctrineParameters());
        $this->assertSame([], $attribute->getFilters());
        $this->assertSame([], $attribute->getDefaultOrdering());
        $this->assertTrue($attribute->isReturnPaginator());
        $this->assertNull($attribute->getNameConverter());
    }

    public function testCustomConfiguration(): void
    {
        $nameConverter = $this->createStub(NameConverterInterface::class);

        $attribute = new MapEntityCollection(
            class: 'App\Entity\Order',
            queryString: 'query',
            queryMapping: ['page' => MappingType::PAGE, 'size' => MappingType::LIMIT],
            doctrineParameters: ['status' => 'active'],
            filters: [DummyDoctrineFilter::class],
            defaultOrdering: ['createdAt' => MapEntityCollection::ORDERING_DESC],
            returnPaginator: false,
            nameConverter: $nameConverter,
        );

        $this->assertSame('App\Entity\Order', $attribute->getClass());
        $this->assertSame('query', $attribute->getQueryString());
        $this->assertSame(['page' => MappingType::PAGE, 'size' => MappingType::LIMIT], $attribute->getQueryMapping());
        $this->assertSame(['status' => 'active'], $attribute->getDoctrineParameters());
        $this->assertSame([DummyDoctrineFilter::class], $attribute->getFilters());
        $this->assertSame(['createdAt' => MapEntityCollection::ORDERING_DESC], $attribute->getDefaultOrdering());
        $this->assertFalse($attribute->isReturnPaginator());
        $this->assertSame($nameConverter, $attribute->getNameConverter());
    }
}

final class DummyDoctrineFilter implements DoctrineFilterInterface
{
    public function applyFilter(
        QueryBuilder $queryBuilder,
        MapEntityCollection $attribute,
        Request $request,
        ?object $queryStringObject = null
    ): void {}
}
