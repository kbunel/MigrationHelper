<?php

namespace MigrationHelperSF4\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

class MigrationHelperSF4Extension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
		$loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
		$loader->load('services.yaml');

		$configuration = new Configuration();
		$config = $this->processConfiguration($configuration, $configs);

        $configManagerDefinition = $container->getDefinition('config_manager');
        $configManagerDefinition->setArgument('$projectName', $config['project_name']);
    }
}
