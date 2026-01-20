<?php

declare(strict_types=1);

namespace Evyex\SymfonyExtender;

use Evyex\SymfonyExtender\Security\ChangeIsGrantedListenerPriorityPass;
use Evyex\SymfonyExtender\Validator\PhoneNumberValidator;
use Evyex\SymfonyExtender\ValueResolver\MapEntityCollection\EntityCollectionValueResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class SymfonyExtenderBundle extends AbstractBundle
{
    private ContainerBuilder $containerBuilder;

    public function build(ContainerBuilder $container): void
    {
        $this->containerBuilder = $container;

        $this->registerService(EntityCollectionValueResolver::class, 'controller.targeted_value_resolver');
        $this->registerService(PhoneNumberValidator::class, 'validator.constraint_validator');

        $container->addCompilerPass(new ChangeIsGrantedListenerPriorityPass());
    }

    private function registerService(string $class, string $tag): void
    {
        $this->containerBuilder
            ->register($class)
            ->addTag($tag)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;
    }
}
