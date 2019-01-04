<?php

namespace MigrationHelperSF4\Analyzer;

use MigrationHelperSF4\Manager\Tools;
use MigrationHelperSF4\Manager\FileManager;
use Symfony\Component\Yaml\Yaml;

class ServiceAnalyzer
{
    public const DOCTRINE_EVENT_LISTENER          = 'doctrine.event_listener';
    public const DOCTRINE_EVENT_SUBSCRIBER        = 'doctrine.event_subscriber';
    public const DOCTRINE_ORM_ENTITY_LISTENER     = 'doctrine.orm.entity_listener';
    public const JMS_SUBSCRIBING_HANDLER          = 'jms_serializer.subscribing_handler';
    public const JMS_EVENT_SUBSCRIBER             = 'jms_serializer.event_subscriber';
    public const VALIDATOR_CONSTRAINT_VALIDATOR   = 'validator.constraint_validator';
    public const KERNEL_EVENT_LISTENER            = 'kernel.event_listener';
    public const KERNEL_EVENT_SUBSCRIBER          = 'kernel.event_subscriber';

    private $tools;
    private $configs = [];

    public function __construct(Tools $tools)
    {
        $this->tools = $tools;
    }

    public function __call(string $method, array $args)
    {
        $tag = $this->tools->getConstanteFromIsMethod(self::class, $method);

        return $this->isTagged($args[0], $tag);
    }

    private function isTagged(string $filePath, string $tag): bool
    {
        $pathInfo = pathInfo($filePath);
        $namespace = $this->tools->getNamespace($filePath);
        $services = $this->getServicesFromConfig();

        if (!isset($services[$namespace], $services[$namespace]['tags'])) {
            return false;
        }

        foreach ($services[$namespace]['tags'] as $t) {
            if ($t['name'] == $tag) {
                return true;
            }
        }

        return false;
    }

    private function getServicesFromConfig(): array
    {
        $services = [];
        $configs = $this->getConfigs();

        foreach ($configs as $config) {
            $services += array_filter($config, function(string $key) {
                return $key == 'services';
            }, ARRAY_FILTER_USE_KEY);
        }

        return $services['services'];
    }

    private function getConfigs(array $files = FileManager::CONFIG_FILES): array
    {
        if ($this->configs) {
            return $this->configs;
        }

        $configs = [];
        foreach ($files as $file) {
            $configs[$file] = Yaml::parseFile($file);

            if (isset($configs[$file]['imports'])) {
                foreach ($configs[$file]['imports'] as $import) {
                    $directory = pathinfo($file)['dirname'];
                    $filePath = $directory . DIRECTORY_SEPARATOR . $import['resource'];
                    $configs[$filePath] = $this->getConfigs([$filePath]);
                }
            }
        }

        $this->configs = $configs;

        return $configs;
    }
}