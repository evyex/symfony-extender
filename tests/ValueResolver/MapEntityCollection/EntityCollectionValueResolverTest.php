<?php

declare(strict_types=1);

namespace Evyex\SymfonyExtender\Tests\ValueResolver\MapEntityCollection;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Evyex\SymfonyExtender\ValueResolver\MapEntityCollection\EntityCollectionValueResolver;
use Evyex\SymfonyExtender\ValueResolver\MapEntityCollection\MapEntityCollection;
use Evyex\SymfonyExtender\ValueResolver\MapEntityCollection\MappingType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
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
            registry: $this->createMock(ManagerRegistry::class),
            tokenStorage: $this->createMock(TokenStorageInterface::class),
            container: $this->createMock(ContainerInterface::class),
            propertyInfoExtractor: $this->createMock(PropertyInfoExtractorInterface::class),
            propertyAccessor: $this->createMock(PropertyAccessorInterface::class),
        );

        $resolved = $resolver->resolve(new Request(), $argument);

        $this->assertSame([$attribute], $resolved);
    }

    public function testMapEntityCollectionReplacesArgumentWithResolvedCollection(): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn(['row-1', 'row-2']);

        $expr = $this->createMock(Expr::class);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['expr', 'getDQLPart', 'getQuery'])
            ->getMock()
        ;
        $queryBuilder->method('expr')->willReturn($expr);
        $queryBuilder->method('getDQLPart')->with('orderBy')->willReturn([]);
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder'])
            ->getMock()
        ;
        $repository->method('createQueryBuilder')->with(EntityCollectionValueResolver::QUERY_ROOT_ALIAS)->willReturn($queryBuilder);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with('App\Entity\Product')->willReturn($entityManager);

        $attribute = new MapEntityCollection('App\Entity\Product', returnPaginator: false);
        $event = $this->createControllerArgumentsEvent(['first', $attribute, 'third']);

        $resolver = $this->createResolver(
            registry: $registry,
            tokenStorage: $this->createMock(TokenStorageInterface::class),
            container: $this->createMock(ContainerInterface::class),
            propertyInfoExtractor: $this->createMock(PropertyInfoExtractorInterface::class),
            propertyAccessor: $this->createMock(PropertyAccessorInterface::class),
        );

        $resolver->mapEntityCollection($event);

        $this->assertSame(['first', ['row-1', 'row-2'], 'third'], $event->getArguments());
    }

    public function testMapEntityCollectionThrowsWhenManagerIsNotEntityManager(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);

        $event = $this->createControllerArgumentsEvent([
            new MapEntityCollection('App\Entity\Missing', returnPaginator: false),
        ]);

        $resolver = $this->createResolver(
            registry: $registry,
            tokenStorage: $this->createMock(TokenStorageInterface::class),
            container: $this->createMock(ContainerInterface::class),
            propertyInfoExtractor: $this->createMock(PropertyInfoExtractorInterface::class),
            propertyAccessor: $this->createMock(PropertyAccessorInterface::class),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No manager found for class "App\Entity\Missing".');

        $resolver->mapEntityCollection($event);
    }

    public function testMapEntityCollectionRejectsUnsupportedDoctrineLimitParameter(): void
    {
        $expr = $this->createMock(Expr::class);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['expr'])
            ->getMock()
        ;
        $queryBuilder->method('expr')->willReturn($expr);

        $repository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder'])
            ->getMock()
        ;
        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        $registry = $this->createMock(ManagerRegistry::class);
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
            tokenStorage: $this->createMock(TokenStorageInterface::class),
            container: $this->createMock(ContainerInterface::class),
            propertyInfoExtractor: $this->createMock(PropertyInfoExtractorInterface::class),
            propertyAccessor: $this->createMock(PropertyAccessorInterface::class),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Doctrine parameter "pageSize" is not supported.');

        $resolver->mapEntityCollection($event);
    }

    private function createResolver(
        ManagerRegistry $registry,
        TokenStorageInterface $tokenStorage,
        ContainerInterface $container,
        PropertyInfoExtractorInterface $propertyInfoExtractor,
        PropertyAccessorInterface $propertyAccessor,
    ): EntityCollectionValueResolver {
        return new EntityCollectionValueResolver(
            registry: $registry,
            tokenStorage: $tokenStorage,
            container: $container,
            propertyInfoExtractor: $propertyInfoExtractor,
            propertyAccessor: $propertyAccessor,
        );
    }

    private function createControllerArgumentsEvent(array $arguments): ControllerArgumentsEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $controller = static fn () => null;

        return new ControllerArgumentsEvent(
            kernel: $kernel,
            controller: $controller,
            arguments: $arguments,
            request: $request,
            requestType: HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
