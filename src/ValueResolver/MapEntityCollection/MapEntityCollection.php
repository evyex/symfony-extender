<?php

declare(strict_types=1);

namespace Evyex\SymfonyExtender\ValueResolver\MapEntityCollection;

use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class MapEntityCollection extends ValueResolver
{
    public const ORDERING_ASC = 'ASC';
    public const ORDERING_DESC = 'DESC';

    /**
     * @param class-string                                          $class
     * @param array<string, MappingType|string>                     $queryMapping
     * @param array<string, mixed>                                  $doctrineParameters
     * @param class-string<DoctrineFilterInterface>[]               $filters
     * @param array<string, self::ORDERING_ASC|self::ORDERING_DESC> $defaultOrdering
     * @param string[]                                              $fetchAssociation
     *
     * @see MapEntityCollection.md Full usage guide and parameter reference.
     */
    public function __construct(
        private readonly string $class,
        private readonly ?string $queryObject = null,
        private readonly array $queryMapping = [],
        private readonly array $doctrineParameters = [],
        private readonly array $filters = [],
        private readonly array $defaultOrdering = [],
        private readonly bool $returnPaginator = true,
        private readonly ?NameConverterInterface $nameConverter = null,
        private readonly array $fetchAssociation = [],
    ) {
        parent::__construct(EntityCollectionValueResolver::class);
    }

    /**
     * @return class-string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    public function getQueryObject(): ?string
    {
        return $this->queryObject;
    }

    /**
     * @return array<string, MappingType|string>
     */
    public function getQueryMapping(): array
    {
        return $this->queryMapping;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDoctrineParameters(): array
    {
        return $this->doctrineParameters;
    }

    /**
     * @return class-string<DoctrineFilterInterface>[]
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * @return array<string, self::ORDERING_ASC|self::ORDERING_DESC>
     */
    public function getDefaultOrdering(): array
    {
        return $this->defaultOrdering;
    }

    public function isReturnPaginator(): bool
    {
        return $this->returnPaginator;
    }

    public function getNameConverter(): ?NameConverterInterface
    {
        return $this->nameConverter;
    }

    /**
     * @return string[]
     */
    public function getFetchAssociation(): array
    {
        return $this->fetchAssociation;
    }
}
