<?php

declare(strict_types=1);

namespace Evyex\SymfonyExtender\Tests\ValueResolver\MapEntityCollection;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Evyex\SymfonyExtender\ValueResolver\MapEntityCollection\EntityCollectionValueResolver;
use Evyex\SymfonyExtender\ValueResolver\MapEntityCollection\MapEntityCollection;
use Evyex\SymfonyExtender\ValueResolver\MapEntityCollection\MappingType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @internal
 */
#[CoversClass(EntityCollectionValueResolver::class)]
class EntityCollectionValueResolverTest extends TestCase
{
    public function testResolveReturnsMapEntityCollectionAttributes(): void
    {
        $attribute = new MapEntityCollection('App\Entity\Product');
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument
            ->expects($this->once())
            ->method('getAttributesOfType')
            ->with(MapEntityCollection::class, ArgumentMetadata::IS_INSTANCEOF)
            ->willReturn([$attribute])
        ;

        $resolver = $this->createResolver(
            registry: $this->createStub(ManagerRegistry::class),
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            container: $this->createStub(ContainerInterface::class),
            propertyInfoExtractor: $this->createStub(PropertyInfoExtractorInterface::class),
            propertyAccessor: $this->createStub(PropertyAccessorInterface::class),
        );

        $resolved = $resolver->resolve(new Request(), $argument);

        $this->assertSame([$attribute], $resolved);
    }

    public function testMapEntityCollectionReplacesArgumentWithResolvedCollection(): void
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn(['row-1', 'row-2']);

        $expr = $this->createStub(Expr::class);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['expr', 'getDQLPart', 'getQuery'])
            ->getMock()
        ;
        $queryBuilder->method('expr')->willReturn($expr);
        $queryBuilder->expects($this->once())->method('getDQLPart')->with('orderBy')->willReturn([]);
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder'])
            ->getMock()
        ;
        $repository->expects($this->once())->method('createQueryBuilder')->with(EntityCollectionValueResolver::QUERY_ROOT_ALIAS)->willReturn($queryBuilder);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->once())->method('getManagerForClass')->with('App\Entity\Product')->willReturn($entityManager);

        $attribute = new MapEntityCollection('App\Entity\Product', returnPaginator: false);
        $event = $this->createControllerArgumentsEvent(['first', $attribute, 'third']);

        $resolver = $this->createResolver(
            registry: $registry,
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            container: $this->createStub(ContainerInterface::class),
            propertyInfoExtractor: $this->createStub(PropertyInfoExtractorInterface::class),
            propertyAccessor: $this->createStub(PropertyAccessorInterface::class),
        );

        $resolver->mapEntityCollection($event);

        $this->assertSame(['first', ['row-1', 'row-2'], 'third'], $event->getArguments());
    }

    public function testMapEntityCollectionReturnsPaginatorWhenEnabled(): void
    {
        $query = $this->createStub(Query::class);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setMaxResults', 'setFirstResult', 'getDQLPart', 'getQuery'])
            ->getMock()
        ;
        $queryBuilder->expects($this->once())->method('setMaxResults')->with(20)->willReturnSelf();
        $queryBuilder->expects($this->once())->method('setFirstResult')->with(0)->willReturnSelf();
        $queryBuilder->expects($this->once())->method('getDQLPart')->with('orderBy')->willReturn(['existing_order']);
        $queryBuilder->method('getQuery')->willReturn($query);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry
            ->expects($this->once())
            ->method('getManagerForClass')
            ->with('App\Entity\Product')
            ->willReturn($this->createEntityManagerWithRepositoryQueryBuilder($queryBuilder))
        ;

        $event = $this->createControllerArgumentsEvent([new MapEntityCollection('App\Entity\Product')]);

        $resolver = $this->createResolver(
            registry: $registry,
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            container: $this->createStub(ContainerInterface::class),
            propertyInfoExtractor: $this->createStub(PropertyInfoExtractorInterface::class),
            propertyAccessor: $this->createStub(PropertyAccessorInterface::class),
        );

        $resolver->mapEntityCollection($event);

        $resolvedArgument = $event->getArguments()[0];
        $this->assertInstanceOf(Paginator::class, $resolvedArgument);
    }

    public function testMapEntityCollectionThrowsWhenManagerIsNotEntityManager(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);

        $event = $this->createControllerArgumentsEvent([
            new MapEntityCollection('App\Entity\Missing', returnPaginator: false),
        ]);

        $resolver = $this->createResolver(
            registry: $registry,
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            container: $this->createStub(ContainerInterface::class),
            propertyInfoExtractor: $this->createStub(PropertyInfoExtractorInterface::class),
            propertyAccessor: $this->createStub(PropertyAccessorInterface::class),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No manager found for class "App\Entity\Missing".');

        $resolver->mapEntityCollection($event);
    }

    public function testMapEntityCollectionRejectsUnsupportedDoctrineLimitParameter(): void
    {
        $expr = $this->createStub(Expr::class);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['expr'])
            ->getMock()
        ;
        $queryBuilder->expects($this->never())->method('expr');

        $repository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder'])
            ->getMock()
        ;
        $repository->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);

        $event = $this->createControllerArgumentsEvent([
            new MapEntityCollection(
                class: 'App\Entity\Product',
                doctrineParameters: ['pageSize' => MappingType::LIMIT],
                returnPaginator: false,
            ),
        ]);

        $resolver = $this->createResolver(
            registry: $registry,
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            container: $this->createStub(ContainerInterface::class),
            propertyInfoExtractor: $this->createStub(PropertyInfoExtractorInterface::class),
            propertyAccessor: $this->createStub(PropertyAccessorInterface::class),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Doctrine parameter "pageSize" is not supported.');

        $resolver->mapEntityCollection($event);
    }

    public function testMapEntityCollectionAppliesQueryStringMapping(): void
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([]);

        $expr = $this->createMock(Expr::class);
        $comparison = $this->createStub(Comparison::class);
        $expr
            ->expects($this->once())
            ->method('eq')
            ->with('ecr.status', $this->matchesRegularExpression('/^:ecr_status_\d+$/'))
            ->willReturn($comparison)
        ;

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['expr', 'setMaxResults', 'setFirstResult', 'setParameter', 'andWhere', 'getDQLPart', 'getQuery'])
            ->getMock()
        ;
        $queryBuilder->method('expr')->willReturn($expr);
        $queryBuilder->expects($this->once())->method('setMaxResults')->with(20)->willReturnSelf();
        $queryBuilder->expects($this->once())->method('setFirstResult')->with(20)->willReturnSelf();
        $queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->with($this->matchesRegularExpression('/^:ecr_status_\d+$/'), 'active')
            ->willReturnSelf()
        ;
        $queryBuilder->expects($this->once())->method('andWhere')->with($comparison)->willReturnSelf();
        $queryBuilder->expects($this->once())->method('getDQLPart')->with('orderBy')->willReturn(['existing_order']);
        $queryBuilder->method('getQuery')->willReturn($query);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry
            ->expects($this->once())
            ->method('getManagerForClass')
            ->with('App\Entity\Product')
            ->willReturn($this->createEntityManagerWithRepositoryQueryBuilder($queryBuilder))
        ;

        $propertyInfoExtractor = $this->createMock(PropertyInfoExtractorInterface::class);
        $propertyInfoExtractor
            ->expects($this->once())
            ->method('getProperties')
            ->with(MapEntityCollectionQueryInput::class)
            ->willReturn(['page', 'size', 'status', 'ignored'])
        ;

        $propertyAccessor = $this->createStub(PropertyAccessorInterface::class);
        $propertyAccessor
            ->method('getValue')
            ->willReturnCallback(static function (object $object, string $property): mixed {
                return $object->{$property};
            })
        ;

        $queryInput = new MapEntityCollectionQueryInput(page: 2, size: 20, status: 'active', ignored: 'skip');
        $attribute = new MapEntityCollection(
            class: 'App\Entity\Product',
            queryObject: 'query',
            queryMapping: [
                'page' => MappingType::PAGE,
                'size' => MappingType::LIMIT,
                'ignored' => MappingType::IGNORE,
            ],
            returnPaginator: false,
        );
        $event = $this->createControllerArgumentsEvent(
            arguments: [$attribute, $queryInput],
            controller: static function (array $collection, MapEntityCollectionQueryInput $query): void {},
        );

        $resolver = $this->createResolver(
            registry: $registry,
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            container: $this->createStub(ContainerInterface::class),
            propertyInfoExtractor: $propertyInfoExtractor,
            propertyAccessor: $propertyAccessor,
        );

        $resolver->mapEntityCollection($event);

        $this->assertIsArray($event->getArguments()[0]);
    }

    public function testMapEntityCollectionAppliesDefaultOrderingWhenOrderByIsMissing(): void
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([]);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDQLPart', 'addOrderBy', 'getQuery'])
            ->getMock()
        ;
        $queryBuilder->expects($this->once())->method('getDQLPart')->with('orderBy')->willReturn([]);
        $queryBuilder->expects($this->once())->method('addOrderBy')->with('ecr.createdAt', 'DESC')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry
            ->expects($this->once())
            ->method('getManagerForClass')
            ->with('App\Entity\Product')
            ->willReturn($this->createEntityManagerWithRepositoryQueryBuilder($queryBuilder))
        ;

        $attribute = new MapEntityCollection(
            class: 'App\Entity\Product',
            defaultOrdering: ['createdAt' => MapEntityCollection::ORDERING_DESC],
            returnPaginator: false,
        );
        $event = $this->createControllerArgumentsEvent([$attribute]);

        $resolver = $this->createResolver(
            registry: $registry,
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            container: $this->createStub(ContainerInterface::class),
            propertyInfoExtractor: $this->createStub(PropertyInfoExtractorInterface::class),
            propertyAccessor: $this->createStub(PropertyAccessorInterface::class),
        );

        $resolver->mapEntityCollection($event);
    }

    public function testMapEntityCollectionDoesNotApplyDefaultOrderingWhenOrderByExists(): void
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([]);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDQLPart', 'addOrderBy', 'getQuery'])
            ->getMock()
        ;
        $queryBuilder->expects($this->once())->method('getDQLPart')->with('orderBy')->willReturn(['existing_order']);
        $queryBuilder->expects($this->never())->method('addOrderBy');
        $queryBuilder->method('getQuery')->willReturn($query);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry
            ->expects($this->once())
            ->method('getManagerForClass')
            ->with('App\Entity\Product')
            ->willReturn($this->createEntityManagerWithRepositoryQueryBuilder($queryBuilder))
        ;

        $attribute = new MapEntityCollection(
            class: 'App\Entity\Product',
            defaultOrdering: ['createdAt' => MapEntityCollection::ORDERING_DESC],
            returnPaginator: false,
        );
        $event = $this->createControllerArgumentsEvent([$attribute]);

        $resolver = $this->createResolver(
            registry: $registry,
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            container: $this->createStub(ContainerInterface::class),
            propertyInfoExtractor: $this->createStub(PropertyInfoExtractorInterface::class),
            propertyAccessor: $this->createStub(PropertyAccessorInterface::class),
        );

        $resolver->mapEntityCollection($event);
    }

    public function testFetchAssociationCreatesAndSelectsJoinForRootEntityAssociation(): void
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([]);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDQLPart', 'getAllAliases', 'leftJoin', 'addSelect', 'getQuery'])
            ->getMock()
        ;
        $queryBuilder->expects($this->once())->method('getDQLPart')->with('orderBy')->willReturn([]);
        $queryBuilder
            ->expects($this->exactly(2))
            ->method('getAllAliases')
            ->willReturn([EntityCollectionValueResolver::QUERY_ROOT_ALIAS])
        ;
        $expectedJoins = [
            ['ecr.category', 'c1'],
            ['ecr.images', 'i2'],
        ];
        $joinIndex = 0;
        $queryBuilder
            ->expects($this->exactly(2))
            ->method('leftJoin')
            ->willReturnCallback(function (string $join, string $alias) use (&$joinIndex, $expectedJoins, $queryBuilder): QueryBuilder {
                $this->assertSame($expectedJoins[$joinIndex], [$join, $alias]);
                ++$joinIndex;

                return $queryBuilder;
            })
        ;
        $expectedSelects = ['c1', 'i2'];
        $selectIndex = 0;
        $queryBuilder
            ->expects($this->exactly(2))
            ->method('addSelect')
            ->willReturnCallback(function (string $select) use (&$selectIndex, $expectedSelects, $queryBuilder): QueryBuilder {
                $this->assertSame($expectedSelects[$selectIndex], $select);
                ++$selectIndex;

                return $queryBuilder;
            })
        ;
        $queryBuilder->method('getQuery')->willReturn($query);

        $registry = $this->createStub(ManagerRegistry::class);
        $registry
            ->method('getManagerForClass')
            ->willReturn($this->createEntityManagerWithRepositoryQueryBuilder($queryBuilder))
        ;

        $attribute = new MapEntityCollection(
            class: \stdClass::class,
            returnPaginator: false,
            fetchAssociation: ['category', 'images'],
        );

        $resolver = $this->createResolver(
            registry: $registry,
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            container: $this->createStub(ContainerInterface::class),
            propertyInfoExtractor: $this->createStub(PropertyInfoExtractorInterface::class),
            propertyAccessor: $this->createStub(PropertyAccessorInterface::class),
        );

        $resolver->mapEntityCollection($this->createControllerArgumentsEvent([$attribute]));
    }

    public function testFetchAssociationSelectsExistingJoinAliasWithoutCreatingAnotherJoin(): void
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([]);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDQLPart', 'getAllAliases', 'leftJoin', 'addSelect', 'getQuery'])
            ->getMock()
        ;
        $queryBuilder->expects($this->once())->method('getDQLPart')->with('orderBy')->willReturn([]);
        $queryBuilder
            ->expects($this->once())
            ->method('getAllAliases')
            ->willReturn([EntityCollectionValueResolver::QUERY_ROOT_ALIAS, 'category'])
        ;
        $queryBuilder->expects($this->never())->method('leftJoin');
        $queryBuilder->expects($this->once())->method('addSelect')->with('category')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $registry = $this->createStub(ManagerRegistry::class);
        $registry
            ->method('getManagerForClass')
            ->willReturn($this->createEntityManagerWithRepositoryQueryBuilder($queryBuilder))
        ;

        $attribute = new MapEntityCollection(
            class: \stdClass::class,
            returnPaginator: false,
            fetchAssociation: ['category'],
        );

        $resolver = $this->createResolver(
            registry: $registry,
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            container: $this->createStub(ContainerInterface::class),
            propertyInfoExtractor: $this->createStub(PropertyInfoExtractorInterface::class),
            propertyAccessor: $this->createStub(PropertyAccessorInterface::class),
        );

        $resolver->mapEntityCollection($this->createControllerArgumentsEvent([$attribute]));
    }

    public function testUsesDefaultLimitWhenPageProvidedWithoutExplicitLimit(): void
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([]);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setMaxResults', 'setFirstResult', 'getDQLPart', 'getQuery'])
            ->getMock()
        ;
        $queryBuilder->expects($this->once())->method('setMaxResults')->with(20)->willReturnSelf();
        $queryBuilder->expects($this->once())->method('setFirstResult')->with(20)->willReturnSelf();
        $queryBuilder->expects($this->once())->method('getDQLPart')->with('orderBy')->willReturn(['existing_order']);
        $queryBuilder->method('getQuery')->willReturn($query);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry
            ->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($this->createEntityManagerWithRepositoryQueryBuilder($queryBuilder))
        ;

        $propertyInfoExtractor = $this->createMock(PropertyInfoExtractorInterface::class);
        $propertyInfoExtractor
            ->expects($this->once())
            ->method('getProperties')
            ->willReturn(['page'])
        ;

        $propertyAccessor = $this->createStub(PropertyAccessorInterface::class);
        $propertyAccessor->method('getValue')->willReturnCallback(static fn (object $o, string $p) => $o->{$p});

        $queryInput = new class {
            public int $page = 2;
        };
        $attribute = new MapEntityCollection(
            class: 'App\Entity\Product',
            queryObject: 'query',
            queryMapping: ['page' => MappingType::PAGE],
            returnPaginator: false,
        );
        $event = $this->createControllerArgumentsEvent(
            arguments: [$attribute, $queryInput],
            controller: static function (array $collection, object $query): void {},
        );

        $resolver = $this->createResolver(
            registry: $registry,
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            container: $this->createStub(ContainerInterface::class),
            propertyInfoExtractor: $propertyInfoExtractor,
            propertyAccessor: $propertyAccessor,
        );

        $resolver->mapEntityCollection($event);
    }

    public function testUsesConfiguredDefaultLimit(): void
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([]);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setMaxResults', 'setFirstResult', 'getDQLPart', 'getQuery'])
            ->getMock()
        ;
        $queryBuilder->expects($this->once())->method('setMaxResults')->with(50)->willReturnSelf();
        $queryBuilder->expects($this->once())->method('setFirstResult')->with(50)->willReturnSelf();
        $queryBuilder->expects($this->once())->method('getDQLPart')->with('orderBy')->willReturn(['existing_order']);
        $queryBuilder->method('getQuery')->willReturn($query);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry
            ->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($this->createEntityManagerWithRepositoryQueryBuilder($queryBuilder))
        ;

        $propertyInfoExtractor = $this->createMock(PropertyInfoExtractorInterface::class);
        $propertyInfoExtractor
            ->expects($this->once())
            ->method('getProperties')
            ->willReturn(['page'])
        ;

        $propertyAccessor = $this->createStub(PropertyAccessorInterface::class);
        $propertyAccessor->method('getValue')->willReturnCallback(static fn (object $o, string $p) => $o->{$p});

        $queryInput = new class {
            public int $page = 2;
        };
        $attribute = new MapEntityCollection(
            class: 'App\Entity\Product',
            queryObject: 'query',
            queryMapping: ['page' => MappingType::PAGE],
            returnPaginator: false,
        );
        $event = $this->createControllerArgumentsEvent(
            arguments: [$attribute, $queryInput],
            controller: static function (array $collection, object $query): void {},
        );

        $resolver = $this->createResolver(
            registry: $registry,
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            container: $this->createStub(ContainerInterface::class),
            propertyInfoExtractor: $propertyInfoExtractor,
            propertyAccessor: $propertyAccessor,
            defaultLimit: 50,
        );

        $resolver->mapEntityCollection($event);
    }

    public function testAppliesPaginationWhenOffsetProvidedWithoutPage(): void
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([]);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setMaxResults', 'setFirstResult', 'getDQLPart', 'getQuery'])
            ->getMock()
        ;
        $queryBuilder->expects($this->once())->method('setMaxResults')->with(20)->willReturnSelf();
        $queryBuilder->expects($this->once())->method('setFirstResult')->with(40)->willReturnSelf();
        $queryBuilder->expects($this->once())->method('getDQLPart')->with('orderBy')->willReturn(['existing_order']);
        $queryBuilder->method('getQuery')->willReturn($query);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry
            ->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($this->createEntityManagerWithRepositoryQueryBuilder($queryBuilder))
        ;

        $propertyInfoExtractor = $this->createMock(PropertyInfoExtractorInterface::class);
        $propertyInfoExtractor->expects($this->once())->method('getProperties')->willReturn(['offset']);

        $propertyAccessor = $this->createStub(PropertyAccessorInterface::class);
        $propertyAccessor->method('getValue')->willReturnCallback(static fn (object $o, string $p) => $o->{$p});

        $queryInput = new class {
            public int $offset = 40;
        };
        $attribute = new MapEntityCollection(
            class: 'App\Entity\Product',
            queryObject: 'query',
            queryMapping: ['offset' => MappingType::OFFSET],
            returnPaginator: false,
        );
        $event = $this->createControllerArgumentsEvent(
            arguments: [$attribute, $queryInput],
            controller: static function (array $collection, object $query): void {},
        );

        $resolver = $this->createResolver(
            registry: $registry,
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            container: $this->createStub(ContainerInterface::class),
            propertyInfoExtractor: $propertyInfoExtractor,
            propertyAccessor: $propertyAccessor,
        );

        $resolver->mapEntityCollection($event);
    }

    public function testExplicitOffsetTakesPrecedenceOverPageOffset(): void
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([]);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setMaxResults', 'setFirstResult', 'getDQLPart', 'getQuery'])
            ->getMock()
        ;
        $queryBuilder->expects($this->once())->method('setMaxResults')->with(20)->willReturnSelf();
        $queryBuilder->expects($this->once())->method('setFirstResult')->with(5)->willReturnSelf();
        $queryBuilder->expects($this->once())->method('getDQLPart')->with('orderBy')->willReturn(['existing_order']);
        $queryBuilder->method('getQuery')->willReturn($query);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry
            ->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($this->createEntityManagerWithRepositoryQueryBuilder($queryBuilder))
        ;

        $propertyInfoExtractor = $this->createMock(PropertyInfoExtractorInterface::class);
        $propertyInfoExtractor->expects($this->once())->method('getProperties')->willReturn(['page', 'offset', 'limit']);

        $propertyAccessor = $this->createStub(PropertyAccessorInterface::class);
        $propertyAccessor->method('getValue')->willReturnCallback(static fn (object $o, string $p) => $o->{$p});

        $queryInput = new class {
            public int $page = 3;
            public int $offset = 5;
            public int $limit = 20;
        };
        $attribute = new MapEntityCollection(
            class: 'App\Entity\Product',
            queryObject: 'query',
            queryMapping: [
                'page' => MappingType::PAGE,
                'offset' => MappingType::OFFSET,
                'limit' => MappingType::LIMIT,
            ],
            returnPaginator: false,
        );
        $event = $this->createControllerArgumentsEvent(
            arguments: [$attribute, $queryInput],
            controller: static function (array $collection, object $query): void {},
        );

        $resolver = $this->createResolver(
            registry: $registry,
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            container: $this->createStub(ContainerInterface::class),
            propertyInfoExtractor: $propertyInfoExtractor,
            propertyAccessor: $propertyAccessor,
        );

        $resolver->mapEntityCollection($event);
    }

    #[DataProvider('invalidPaginationProvider')]
    public function testRejectsInvalidPaginationParameters(
        string $property,
        MappingType $mappingType,
        int $value,
        string $expectedMessage,
    ): void {
        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDQLPart'])
            ->getMock()
        ;
        $queryBuilder->expects($this->never())->method('getDQLPart');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry
            ->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($this->createEntityManagerWithRepositoryQueryBuilder($queryBuilder))
        ;

        $propertyInfoExtractor = $this->createMock(PropertyInfoExtractorInterface::class);
        $propertyInfoExtractor->expects($this->once())->method('getProperties')->willReturn([$property]);

        $propertyAccessor = $this->createStub(PropertyAccessorInterface::class);
        $propertyAccessor->method('getValue')->willReturn($value);

        $attribute = new MapEntityCollection(
            class: 'App\Entity\Product',
            queryObject: 'query',
            queryMapping: [$property => $mappingType],
            returnPaginator: false,
        );
        $event = $this->createControllerArgumentsEvent(
            arguments: [$attribute, new \stdClass()],
            controller: static function (array $collection, object $query): void {},
        );

        $resolver = $this->createResolver(
            registry: $registry,
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            container: $this->createStub(ContainerInterface::class),
            propertyInfoExtractor: $propertyInfoExtractor,
            propertyAccessor: $propertyAccessor,
        );

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage($expectedMessage);

        $resolver->mapEntityCollection($event);
    }

    /**
     * @return iterable<string, array{string, MappingType, int, string}>
     */
    public static function invalidPaginationProvider(): iterable
    {
        yield 'zero page' => ['page', MappingType::PAGE, 0, 'Page must be greater than 0'];

        yield 'zero limit' => ['limit', MappingType::LIMIT, 0, 'Limit must be greater than 0'];

        yield 'negative offset' => ['offset', MappingType::OFFSET, -1, 'Offset must be greater than or equal to 0'];
    }

    public function testAppliesDefaultPaginationWhenReturnPaginatorTrueWithQueryObject(): void
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([]);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setMaxResults', 'setFirstResult', 'getDQLPart', 'getQuery'])
            ->getMock()
        ;
        $queryBuilder->expects($this->once())->method('setMaxResults')->with(20)->willReturnSelf();
        $queryBuilder->expects($this->once())->method('setFirstResult')->with(0)->willReturnSelf();
        $queryBuilder->expects($this->once())->method('getDQLPart')->with('orderBy')->willReturn(['existing_order']);
        $queryBuilder->method('getQuery')->willReturn($query);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry
            ->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($this->createEntityManagerWithRepositoryQueryBuilder($queryBuilder))
        ;

        $propertyInfoExtractor = $this->createMock(PropertyInfoExtractorInterface::class);
        $propertyInfoExtractor->expects($this->once())->method('getProperties')->willReturn([]);

        $queryInput = new class {};
        $attribute = new MapEntityCollection(
            class: 'App\Entity\Product',
            queryObject: 'query',
            returnPaginator: true,
        );
        $event = $this->createControllerArgumentsEvent(
            arguments: [$attribute, $queryInput],
            controller: static function (Paginator $collection, object $query): void {},
        );

        $resolver = $this->createResolver(
            registry: $registry,
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            container: $this->createStub(ContainerInterface::class),
            propertyInfoExtractor: $propertyInfoExtractor,
            propertyAccessor: $this->createStub(PropertyAccessorInterface::class),
        );

        $resolver->mapEntityCollection($event);

        $this->assertInstanceOf(Paginator::class, $event->getArguments()[0]);
    }

    private function createResolver(
        ManagerRegistry $registry,
        TokenStorageInterface $tokenStorage,
        ContainerInterface $container,
        PropertyInfoExtractorInterface $propertyInfoExtractor,
        PropertyAccessorInterface $propertyAccessor,
        int $defaultLimit = 20,
    ): EntityCollectionValueResolver {
        return new EntityCollectionValueResolver(
            registry: $registry,
            tokenStorage: $tokenStorage,
            container: $container,
            propertyInfoExtractor: $propertyInfoExtractor,
            propertyAccessor: $propertyAccessor,
            defaultLimit: $defaultLimit,
        );
    }

    private function createControllerArgumentsEvent(array $arguments, ?callable $controller = null): ControllerArgumentsEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = new Request();
        $controller ??= static fn () => null;

        return new ControllerArgumentsEvent(
            kernel: $kernel,
            controller: $controller,
            arguments: $arguments,
            request: $request,
            requestType: HttpKernelInterface::MAIN_REQUEST,
        );
    }

    private function createEntityManagerWithRepositoryQueryBuilder(QueryBuilder $queryBuilder): EntityManagerInterface
    {
        $repository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder'])
            ->getMock()
        ;
        $repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with(EntityCollectionValueResolver::QUERY_ROOT_ALIAS)
            ->willReturn($queryBuilder)
        ;

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        return $entityManager;
    }
}

final class MapEntityCollectionQueryInput
{
    public function __construct(
        public int $page,
        public int $size,
        public string $status,
        public string $ignored,
    ) {}
}
