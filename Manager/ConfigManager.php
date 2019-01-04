<?php

namespace MigrationHelperSF4\Manager;

use Symfony\Component\Yaml\Yaml;
use MigrationHelperSF4\Manager\FileManager;
use MigrationHelperSF4\Model\FileAnalyzed;
use MigrationHelperSF4\Manager\Logger;
use MigrationHelperSF4\Manager\FileAnalyzer;
use MigrationHelperSF4\Manager\Tools;

class ConfigManager
{
    private $fileManager;
    private $projectName;
    private $logger;
    private $tools;

    private const ROUTING_FILE_PATH = 'app/config/routing.yml';
    private const CONFIG_FILE       = 'app/config/config.yml';
    private const BUNDLES_TPL_PATH  = __DIR__ . '/../Resources/views/skeleton/bundle.tpl.php';
    private const BUNDLE_PATH       = 'config/bundles.php';

    public function __construct(FileManager $fileManager, Logger $logger, Tools $tools, string $projectName)
    {
        $this->projectName = $projectName; // eg: Acme\UserBundle\... -> Project name = Acme
        $this->fileManager = $fileManager;
        $this->logger = $logger;
        $this->tools = $tools;
    }

    /**
     * @param FileAnalyzed[]
     */
    public function updateConfig(array $files): void
    {
        $this->updateRouting($files);
        $this->addAppInConfig();
    }

    public function importBundles(): void
    {
        $this->logger->writeln('<info>Importing bundles in config/bundles.php</info>');
        $allBundles = (new \AppKernel('dev', true))->registerBundles();
        $prodBundles = (new \AppKernel('prod', true))->registerBundles();
        $devBundles = [];

        foreach ($allBundles as $bundle) {
            $class = get_class($bundle);
            $found = false;
            foreach ($prodBundles as $key => $pBundle) {
                $pClass = get_class($pBundle);

                if (preg_match('/^' . $this->projectName . '/', $pClass)) {
                    unset($prodBundles[$key]);
                }

                if ($pClass == $class) {
                    $found = true;

                    break;
                }
            }

            if (!$found) {
                $devBundles[] = $bundle;
            }
        }

        $bundleFileContent = $this->fileManager->parseTemplate(self::BUNDLES_TPL_PATH, [
            'prodBundles' => $prodBundles,
            'devBundles' => $devBundles
        ]);

        $this->fileManager->write(self::BUNDLE_PATH, $bundleFileContent);
    }

    /**
     * @param FileAnalyzed[]
     */
    public function addXmlServicesToYamlConfig(array $files): void
    {
        $xmlFiles = $this->tools->getKind($files, fileAnalyzer::FILE_KIND_XML);

        $this->logger->writeln('<info>updating services in config from services.xml files</info>');
        $this->logger->startProgressBar(count($xmlFiles));

        $servicesToAdd = $this->getServicesFromXmlFiles($xmlFiles);
        $this->addServicesToConfig($servicesToAdd);

        $this->logger->finishProgressBar();
    }

    /**
     * @param FileAnalyzed[]
     */
    public function deleteXMLServices(array $files): void
    {
        $this->logger->writeln('<info>Deleting xmlFiles</info>');
        $this->logger->startProgressBar();

        foreach ($files as $file) {
            if ($file->kind == FileAnalyzer::FILE_KIND_XML && preg_match('/services.xml$/', $file->originPath)) {
                $this->logger->advanceProgressBar();
                $this->fileManager->remove($file);
            }
        }

        $this->logger->finishProgressBar();
    }

    public function removeOldConfigs(): void
    {
        $this->logger->writeln('<info>Removing all configs in config.yml</info>');
        $lines = file(self::CONFIG_FILE);
        $key = 0;
        while (isset($lines[$key])) {
            if (preg_match('/\.\.\/src\/' . $this->projectName . '/', $lines[$key])) {
                $lines = $this->removeConfig($lines, $key);
                $key = 0;
            }

            $key++;
        }

        $lines = $this->removeEmptyLinesInDouble($lines);

        $this->fileManager->write(self::CONFIG_FILE, implode($lines));
    }

    private function removeEmptyLinesInDouble(array $lines): array
    {
        $empty = false;
        foreach ($lines as $key => $line) {
            $line = trim($line);

            if (!$empty && empty($line)) {
                $empty = true;
            } elseif ($empty && empty($line)) {
                unset($lines[$key]);
            } elseif ($empty && !empty($line)) {
                $empty = false;
            }
        }

        return $lines;
    }

    private function removeConfig(array $lines, int $key): array
    {
        $nbSpace = function(string $str) {
            preg_match('/^ */', $str, $match);
            preg_replace('/ /', '', $match[0], -1, $count);

            return $count;
        };

        $initialSpace = $nbSpace($lines[$key]);
        while (isset($lines[$key])) {
            $key--;

            if ($nbSpace($lines[$key]) == $initialSpace) {
                $key--;
            }

            if ($nbSpace($lines[$key]) < $initialSpace) {
                break;
            }
        }

        $initialSpace = $nbSpace($lines[$key]);
        unset($lines[$key]);
        while ($nbSpace($lines[++$key]) > $initialSpace) {
            unset($lines[$key]);
        }

        return array_values($lines);
    }

    /**
     * @param FileAnalyzed[]
     */
    private function getServicesFromXmlFiles(array $xmlFiles): array
    {
        $servicesToAdd = [];
        $configServices = Yaml::parseFile(self::CONFIG_FILE)['services'];

        foreach ($xmlFiles as $xmlFile) {
            $this->logger->advanceProgressBar();

            if (!preg_match('/services.xml$/', $xmlFile->originPath)) {
                continue;
            }

            $services = new \SimpleXMLElement(\file_get_contents($xmlFile->originPath));
            if (!$services->services->service) {
                continue;
            }

            foreach ($services->services->service as $service) {
                $class = $service->attributes()['class']->__toString();
                $id = $service->attributes()['id']->__toString();

                if (isset($configServices[$class]) || isset($configServices[$id]) ) {
                    continue;
                }

                foreach ($service->argument as $argument) {
                    if ($argument->attributes()['type']->__toString() != 'service') {
                        $servicesToAdd[$class]['arguments'][] = [
                            'type' => $argument->attributes()->__toString(),
                            'value' => $argument[0]->__toString(),
                        ];
                    }
                }

                if ($service->tag) {
                    $servicesToAdd[$class]['tag'] = [];
                    $i = 0;
                    foreach ($service->tag as $tag) {
                        foreach ($tag->attributes() as $key => $attribute) {
                            $servicesToAdd[$class]['tag'][$i][$key] = $attribute->__toString();
                        }
                        $i++;
                    }
                }
            }
        }

        return $servicesToAdd;
    }

    private function addServicesToConfig(array $servicesToAdd): void
    {
        $servicesToAddLines = $this->getServicesFormatedToBeInjected($servicesToAdd);

        if (!$servicesToAddLines) {
            return;
        }

        $this->addServicesToAddInConfig($servicesToAddLines);
    }

    private function addServicesToAddInConfig(array $servicesToAdd): void
    {
        $lines = file(self::CONFIG_FILE);
        $inServices = false;
        foreach ($lines as $key => $line) {
            if (!$inServices && preg_match ('/^services:/', trim($line))) {
                $inServices = true;
            }

            if (!$inServices) {
                continue;
            }

            if (isset($lines[$key + 1]) && preg_match('/^[a-zA-Z]/', $lines[$key + 1])) {
                array_splice($lines, $key + 1, 0, $servicesToAdd);

                break;
            }
        }

        $this->fileManager->write(self::CONFIG_FILE, implode($lines));
    }

    private function getServicesFormatedToBeInjected(array $servicesToAdd): array
    {
        $lines = [];
        foreach ($servicesToAdd as $key => $service) {
            $lines[] = $this->left(4) . $key . ":\n";
            if (isset($service['arguments'])) {
                $lines[] = $this->left(8) . "arguments:\n";
                foreach ($service['arguments'] as $argument) {
                    if ($argument['type'] == 'expression') {
                        $lines[] = $this->left(12) . "- \"@=" . $argument['value'] . "\"\n";
                    } else {
                        $lines[] = $this->left(12) . "- \"" . $argument['value'] . "\"\n";
                    }
                }
            }
            if (isset($service['tag'])) {
                $lines[] = $this->left(8) . "tags:\n";
                foreach ($service['tag'] as $tag) {
                    $l = $this->left(12) . "- { ";
                    foreach ($tag as $attribute => $value) {
                        $l .= $attribute . ': ' . $value . ' ';
                    }
                    $l .= "}\n";
                }
                $lines[] = $l;
            }
            $lines[] = "\n";
        }

        return $lines;
    }

    private function left(int $spaces): string
    {
        $str = '';

        for ($x = 0; $x < $spaces; $x++) {
            $str .= ' ';
        }

        return $str;
    }

    /**
     * @param FileAnalyzed[]
     */
    private function updateRouting(array $files): void
    {
        $this->logger->writeln('<info>updating routing files</info>');
        $routingfiles = $this->getRoutingFiles($files);
        $mainRoutingLines = file(self::ROUTING_FILE_PATH);

        $this->logger->startProgressBar(count($routingfiles));
        foreach ($mainRoutingLines as $key => $line) {
            $resourcePath = preg_replace('/(^[a-zA-Z0-9_: ]+|")/', '', trim($line));

            if (preg_match('/^resource/', trim($line)) && isset($routingfiles[$resourcePath])) {

                preg_match('/[a-zA-Z0-9]+Bundle/', $routingfiles[$resourcePath]->originPath, $bundleName);
                $fileName = lcfirst(str_replace('Bundle', '', $bundleName[0])) . '.yaml';
                $routePath = './routes/' . $fileName;

                $mainRoutingLines[$key] = preg_replace('/"\@[a-zA-Z\/\.]+"/', $routePath, $line);
                $this->logger->advanceProgressBar();
            }
        }

        $this->fileManager->write(self::ROUTING_FILE_PATH, implode($mainRoutingLines));

        $this->logger->finishProgressBar();
    }

    /**
     * @param FileAnalyzed[]
     */
    private function getRoutingFiles(array $files): array
    {
        $routingFiles = [];
        foreach ($files as $file) {
            if ($file->kind == FileAnalyzer::FILE_KIND_ROUTING) {
                $routingFiles[$this->convertPathInResourcePath($file->originPath)] = $file;
            }
        }

        return $routingFiles;
    }

    private function convertPathInResourcePath(string $path): string
    {
        $resourcePath = str_replace('src/', '', $path);
        if (preg_match('/^[a-zA-Z0-9_]+\/[a-zA-Z0-9_]+Bundle/', $resourcePath, $match)) {
            $resourcePath = preg_replace('/^[a-zA-Z0-9_]+\/[a-zA-Z0-9_]+Bundle/', str_replace('/', '', $match[0]), $resourcePath);
        }

        return '@' . $resourcePath;
    }

    private function addAppInConfig(): void
    {
        $this->logger->writeln('<info>Updating config file</info>');
        $config = Yaml::parseFile(self::CONFIG_FILE);

        if (isset($config['services']['App\\'])) {
            return;
        }

        $lines = file(self::CONFIG_FILE);
        foreach ($lines as $key => $line) {
            $line = trim($line);

            if (preg_match('/^services/', $line)) {
                array_splice($lines, $key + 1, 0, ["    App\\:\n"]);
                array_splice($lines, $key + 2, 0, ["        resource: '../../src/*'\n"]);
                array_splice($lines, $key + 3, 0, ["\n"]);

                break;
            }
        }

        $this->fileManager->write(self::CONFIG_FILE, implode($lines));
    }
}