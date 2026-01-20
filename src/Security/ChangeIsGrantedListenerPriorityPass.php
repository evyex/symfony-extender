<?php

declare(strict_types=1);

namespace Evyex\SymfonyExtender\Security;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\KernelEvents;

final class ChangeIsGrantedListenerPriorityPass implements CompilerPassInterface
{
    private const SERVICE_ID = 'security.listener.is_granted_attribute';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::SERVICE_ID)) {
            return;
        }

        $container
            ->getDefinition(self::SERVICE_ID)
            ->clearTags()
            ->addTag(
                'kernel.event_subscriber',
                [
                    'event' => KernelEvents::CONTROLLER_ARGUMENTS,
                    'method' => 'onKernelControllerArguments',
                    'priority' => -5,
                ]
            )
        ;
    }
}
