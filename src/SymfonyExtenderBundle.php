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
    public function build(ContainerBuilder $container): void
    {
        $container
            ->register(EntityCollectionValueResolver::class)
            ->addTag('controller.targeted_value_resolver')
        ;

        $container
            ->register(PhoneNumberValidator::class)
            ->addTag('validator.constraint_validator')
        ;

        $container->addCompilerPass(new ChangeIsGrantedListenerPriorityPass());
    }
}
