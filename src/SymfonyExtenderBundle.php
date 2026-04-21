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

    public const SECTION_PHONE_NUMBER = 'phone_number';
    public const KEY_CLEAN_STRING = 'clean_string';
    public const KEY_PATTERN = 'pattern';

    public function configure(DefinitionConfigurator $definition): void
    {
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $definition->rootNode();

        $this->createNode($rootNode, self::SECTION_ENTITY_COLLECTION)
            ->integerNode(self::KEY_DEFAULT_LIMIT)->min(1)->defaultValue(self::VALUE_DEFAULT_LIMIT)
        ;
        $this->createNode($rootNode, self::SECTION_IS_GRANTED_LISTENER)->booleanNode(self::KEY_ENABLED)->defaultTrue();

        $node = $this->createNode($rootNode, self::SECTION_PHONE_NUMBER);
        $node->booleanNode(self::KEY_CLEAN_STRING)->defaultTrue();
        $node->stringNode(self::KEY_PATTERN)->defaultValue(PhoneNumberValidator::DEFAULT_PATTERN);
    }

    /**
     * @param array{
     *     entity_collection: array{default_limit: int},
     *     is_granted_listener: array{enabled: bool},
     *     phone_number: array{clean_string: bool, pattern: string}
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

        $container->services()
            ->set(PhoneNumberValidator::class)
            ->tag('validator.constraint_validator')
            ->autoconfigure()
            ->autowire()
            ->arg('$cleanString', $config[self::SECTION_PHONE_NUMBER][self::KEY_CLEAN_STRING])
            ->arg('$pattern', $config[self::SECTION_PHONE_NUMBER][self::KEY_PATTERN])
        ;
    }

    private function createNode(ArrayNodeDefinition $rootNode, string $name): NodeBuilder
    {
        return $rootNode->children()->arrayNode($name)->addDefaultsIfNotSet()->children();
    }
}
