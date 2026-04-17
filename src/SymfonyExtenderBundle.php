<?php

declare(strict_types=1);

namespace Evyex\SymfonyExtender;

use Evyex\SymfonyExtender\Security\IsGrantedAttributeListenerDecorator;
use Evyex\SymfonyExtender\Validator\PhoneNumberValidator;
use Evyex\SymfonyExtender\ValueResolver\MapEntityCollection\EntityCollectionValueResolver;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class SymfonyExtenderBundle extends AbstractBundle
{
    public const SECTION_ENTITY_COLLECTION = 'entity_collection';
    public const KEY_DEFAULT_LIMIT = 'default_limit';
    public const VALUE_DEFAULT_LIMIT = 20;

    public function configure(DefinitionConfigurator $definition): void
    {
        $entityCollection = $definition->rootNode()->children()->arrayNode(self::SECTION_ENTITY_COLLECTION);
        $entityCollection->addDefaultsIfNotSet();
        $entityCollection->children()
            ->integerNode(self::KEY_DEFAULT_LIMIT)->defaultValue(self::VALUE_DEFAULT_LIMIT)->min(1)
        ;
    }

    /**
     * @param array{entity_collection: array{default_limit: int}} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()
            ->set(EntityCollectionValueResolver::class)
            ->tag('controller.targeted_value_resolver')
            ->autoconfigure()
            ->autowire()
            ->arg('$defaultLimit', $config[self::SECTION_ENTITY_COLLECTION][self::KEY_DEFAULT_LIMIT])
        ;
    }

    public function build(ContainerBuilder $container): void
    {
        $this->registerService($container, PhoneNumberValidator::class, 'validator.constraint_validator');
        $this->registerService($container, IsGrantedAttributeListenerDecorator::class, 'security.listener.is_granted_attribute');
    }

    private function registerService(ContainerBuilder $container, string $class, string $tag): void
    {
        $container
            ->register($class)
            ->addTag($tag)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;
    }
}
