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
     * @see MapEntityCollection.md Full usage guide and parameter reference.
     *
     * @param class-string                                                                        $class
     * @param class-string<DoctrineFilterInterface>[]                                             $filters
     * @param array<string, MapEntityCollection::ORDERING_ASC|MapEntityCollection::ORDERING_DESC> $defaultOrdering
     * @param array<string, mixed>                                                                $doctrineParameters
     * @param array<string, MappingType|string>                                                   $queryMapping
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
}
