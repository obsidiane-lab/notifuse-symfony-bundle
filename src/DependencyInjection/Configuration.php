<?php

namespace Notifuse\SymfonyBundle\DependencyInjection;

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
                ->scalarNode('notification_center_url')
                    ->info('URL where the notification center assets are served')
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
                ->scalarNode('default_locale')
                    ->info('Locale that will be passed to the notification center embed')
                    ->defaultValue('en')
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
                ->arrayNode('notification_center')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('embed_path')
                            ->info('Path against the notification center base URL to render the widget')
                            ->defaultValue('/notification-center')
                        ->end()
                        ->scalarNode('script_element_id')
                            ->info('ID attribute for the generated notification center <script> tag')
                            ->defaultValue('notifuse-notification-center')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
