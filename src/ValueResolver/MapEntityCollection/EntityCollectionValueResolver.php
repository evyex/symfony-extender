<?php

declare(strict_types=1);

namespace Evyex\SymfonyExtender\ValueResolver\MapEntityCollection;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class EntityCollectionValueResolver implements ValueResolverInterface
{
    public const QUERY_ROOT_ALIAS = 'ecr';

    public function __construct(
        private ManagerRegistry $registry,
        private TokenInterface $token,
        #[AutowireLocator(DoctrineFilterInterface::class)]
        private ContainerInterface $container,
        private PropertyInfoExtractorInterface $propertyInfoExtractor,
        private PropertyAccessorInterface $propertyAccessor
    ) {}

    /**
     * @return MapEntityCollection[]
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        return $argument->getAttributesOfType(MapEntityCollection::class, ArgumentMetadata::IS_INSTANCEOF);
    }

    #[AsEventListener(priority: -10)]
    public function mapEntityCollection(ControllerArgumentsEvent $event): void
    {
        $arguments = $event->getArguments();
        foreach ($arguments as $key => $argument) {
            if ($argument instanceof MapEntityCollection) {
                $arguments[$key] = $this->doResolve($argument, $event);
                $event->setArguments($arguments);

                break;
            }
        }
    }

    /**
     * @return array<mixed, mixed>|Paginator<mixed>
     */
    private function doResolve(MapEntityCollection $attribute, ControllerArgumentsEvent $event): array|Paginator
    {
        $objectManager = $this->registry->getManagerForClass($attribute->getClass());

        if (!$objectManager instanceof EntityManagerInterface) {
            throw new \RuntimeException(sprintf('No manager found for class "%s".', $attribute->getClass()));
        }

        $queryBuilder = $objectManager
            ->getRepository($attribute->getClass())
            ->createQueryBuilder(self::QUERY_ROOT_ALIAS)
        ;

        $this->doctrineParameterProcessing($queryBuilder, $attribute->getDoctrineParameters(), $event);

        /** @var null|object $queryStringObject */
        $queryStringObject = $attribute->getQueryString()
            ? $event->getNamedArguments()[$attribute->getQueryString()] : null;

        foreach ($attribute->getFilters() as $filter) {
            /** @var DoctrineFilterInterface $queryFilter */
            $queryFilter = $this->container->get($filter);
            $queryFilter->applyFilter($queryBuilder, $attribute, $event->getRequest(), $queryStringObject);
        }

        if ($queryStringObject) {
            $limit = null;
            $offset = null;
            $page = null;
            foreach ($this->propertyInfoExtractor->getProperties($queryStringObject::class) ?? [] as $property) {
                $propertyMapping = $attribute->getQueryMapping()[$property] ?? null;
                $value = $this->propertyAccessor->getValue($queryStringObject, $property);

                switch ($propertyMapping) {
                    case MappingType::LIMIT:
                        $limit = $value;

                        break;

                    case MappingType::OFFSET:
                        $offset = $value;

                        break;

                    case MappingType::PAGE:
                        $page = $value;

                        break;

                    case MappingType::IGNORE:
                        break;

                    default:
                        $this->addCondition($queryBuilder, $property, $value);
                }
            }

            if (is_integer($limit)) {
                $queryBuilder->setMaxResults($limit);
            }

            if (is_integer($offset)) {
                $queryBuilder->setFirstResult($offset);
            }

            if (is_integer($page) && is_integer($limit)) {
                $queryBuilder->setFirstResult(($page - 1) * $limit);
            }
        }

        if (!$queryBuilder->getDQLPart('orderBy')) {
            foreach ($attribute->getDefaultOrdering() as $property => $direction) {
                $queryBuilder->addOrderBy(
                    $this->getQueryProperty(
                        $attribute->getNameConverter()
                            ? $attribute->getNameConverter()->denormalize($property) : $property
                    ),
                    $direction
                );
            }
        }

        return $attribute->isReturnPaginator() ? new Paginator($queryBuilder) : (array) $queryBuilder->getQuery()->getResult();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function doctrineParameterProcessing(
        QueryBuilder $queryBuilder,
        array $parameters,
        ControllerArgumentsEvent $event
    ): void {
        foreach ($parameters as $key => $value) {
            if (in_array($value, [MappingType::LIMIT, MappingType::PAGE, MappingType::OFFSET], true)) {
                throw new \LogicException(sprintf('Doctrine parameter "%s" is not supported.', $key));
            }

            $this->addCondition(
                queryBuilder: $queryBuilder,
                propertyName: $key,
                value: $this->buildValue($value, $event->getRequest()->attributes->all())
            );
        }
    }

    private function addCondition(QueryBuilder $queryBuilder, string $propertyName, mixed $value): void
    {
        if (MappingType::IGNORE === $value) {
            return;
        }

        $expr = $queryBuilder->expr();
        $queryPropertyAlias = $this->getQueryProperty($propertyName);

        if (MappingType::NULL === $value) {
            $expression = $expr->isNull($queryPropertyAlias);
        } elseif (MappingType::NOT_NULL === $value) {
            $expression = $expr->isNotNull($queryPropertyAlias);
        } elseif (is_array($value)) {
            $expression = $expr->in($queryPropertyAlias, $value);
        } else {
            $expression = $expr->eq($queryPropertyAlias, $value);
        }

        $queryBuilder->andWhere($expression);
    }

    /**
     * @param array<string, mixed> $additionalData
     */
    private function buildValue(mixed $value, array $additionalData = []): mixed
    {
        if (is_string($value) && isset($additionalData[$value])) {
            return $additionalData[$value];
        }

        if ($value instanceof Expression) {
            return (new ExpressionLanguage())->evaluate($value, ['user' => $this->token->getUser()]);
        }

        return $value;
    }

    private function getQueryProperty(string $property): string
    {
        return sprintf('%s.%s', self::QUERY_ROOT_ALIAS, $property);
    }
}
