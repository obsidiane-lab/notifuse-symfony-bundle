<?php

namespace Obsidiane\Notifuse\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('notifuse');
        $root = $treeBuilder->getRootNode();
        $root
            ->children()
                ->scalarNode('api_base_url')
                    ->info('Base URL used to reach the Notifuse API')
                    ->cannotBeEmpty()
                    ->defaultValue('https://localapi.notifuse.com:4000')
                ->end()
                ->scalarNode('workspace_id')
                    ->info('Workspace identifier to scope requests')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('workspace_api_key')
                    ->info('API key / bearer token that the bundle sends with API calls')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('http_client_options')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->floatNode('timeout')
                            ->info('Timeout in seconds for outbound requests')
                            ->defaultValue(10.0)
                        ->end()
                        ->integerNode('max_redirects')
                            ->info('Maximum number of redirects the HTTP client should follow')
                            ->defaultValue(5)
                        ->end()
                        ->booleanNode('verify_peer')
                            ->info('Whether SSL peer verification is enabled')
                            ->defaultTrue()
                        ->end()
                        ->arrayNode('headers')
                            ->info('Additional headers appended to every request')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
                
            ->end();

        return $treeBuilder;
    }
}
