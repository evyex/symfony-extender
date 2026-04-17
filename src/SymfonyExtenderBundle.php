<?php

declare(strict_types=1);

namespace Evyex\SymfonyExtender;

use Evyex\SymfonyExtender\Security\IsGrantedAttributeListenerDecorator;
use Evyex\SymfonyExtender\Validator\PhoneNumberValidator;
use Evyex\SymfonyExtender\ValueResolver\MapEntityCollection\EntityCollectionValueResolver;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class SymfonyExtenderBundle extends AbstractBundle
{
    public const SECTION_ENTITY_COLLECTION = 'entity_collection';
    public const KEY_DEFAULT_LIMIT = 'default_limit';
    public const VALUE_DEFAULT_LIMIT = 20;

    public const SECTION_IS_GRANTED_LISTENER = 'is_granted_listener';
    public const KEY_ENABLED = 'enabled';

    public function configure(DefinitionConfigurator $definition): void
    {
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $definition->rootNode();

        $this->createNode($rootNode, self::SECTION_ENTITY_COLLECTION)
            ->integerNode(self::KEY_DEFAULT_LIMIT)->min(1)->defaultValue(self::VALUE_DEFAULT_LIMIT)
        ;
        $this->createNode($rootNode, self::SECTION_IS_GRANTED_LISTENER)->booleanNode(self::KEY_ENABLED)->defaultTrue();
    }

    /**
     * @param array{
     *     entity_collection: array{default_limit: int},
     *     is_granted_listener: array{enabled: bool}
     *     } $config
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

        if ($config[self::SECTION_IS_GRANTED_LISTENER][self::KEY_ENABLED]) {
            $container->services()
                ->set(IsGrantedAttributeListenerDecorator::class)
                ->tag('security.listener.is_granted_attribute')
                ->autoconfigure()
                ->autowire()
            ;
        }
    }

    public function build(ContainerBuilder $container): void
    {
        $container
            ->register(PhoneNumberValidator::class)
            ->addTag('validator.constraint_validator')
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;
    }

    private function createNode(ArrayNodeDefinition $rootNode, string $name): NodeBuilder
    {
        return $rootNode->children()->arrayNode($name)->addDefaultsIfNotSet()->children();
    }
}
