<?php

declare(strict_types=1);

namespace Evyex\SymfonyExtender\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Evyex\SymfonyExtender\SymfonyExtenderBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new SecurityBundle(),
            new DoctrineBundle(),
            new SymfonyExtenderBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(
            function (ContainerBuilder $container) {
                $container->loadFromExtension(
                    'framework',
                    [
                        'test' => true,
                        'router' => [
                            'resource' => false,
                            'type' => 'service',
                        ],
                    ]
                );

                $container->loadFromExtension(
                    'doctrine',
                    [
                        'dbal' => [
                            'driver' => 'pdo_sqlite',
                            'path' => ':memory:',
                        ],
                        'orm' => [
                            'auto_mapping' => false,
                        ],
                    ]
                );

                $container->loadFromExtension(
                    'security',
                    [
                        'providers' => [
                            'test_provider' => [
                                'memory' => [
                                    'users' => [],
                                ],
                            ],
                        ],
                        'firewalls' => [
                            'main' => [
                                'provider' => 'test_provider',
                            ],
                        ],
                    ]
                );
            }
        );
    }
}
