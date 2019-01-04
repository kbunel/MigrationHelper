<?php

namespace MigrationHelperSF4\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('migration_helper_sf4');

        $rootNode
            ->children()
                ->scalarNode('project_name')->end()
            ->end()
        ;

        return $treeBuilder;
    }
}