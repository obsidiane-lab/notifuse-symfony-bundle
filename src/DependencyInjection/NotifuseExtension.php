<?php

namespace Obsidiane\Notifuse\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class NotifuseExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('notifuse.api_base_url', $config['api_base_url']);
        $container->setParameter('notifuse.workspace_id', $config['workspace_id']);
        $container->setParameter('notifuse.workspace_api_key', $config['workspace_api_key']);
        $container->setParameter('notifuse.http_client_options', $config['http_client_options']);
        

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }
}
