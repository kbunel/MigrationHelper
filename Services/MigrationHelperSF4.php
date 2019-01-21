<?php

namespace MigrationHelperSF4\Services;

use FileAnalyzer\Services\FileAnalyzer;
use FileAnalyzer\Services\Tools;
use FileAnalyzer\Services\Logger;
use FileAnalyzer\Model\FileAnalyzed;
use MigrationHelperSF4\Manager\FileManager;
use MigrationHelperSF4\Manager\ContentManager;
use MigrationHelperSF4\Manager\ConfigManager;

class MigrationHelperSF4
{
    private $fileAnalyzer;
    private $fileManager;
    private $contentManager;
    private $tools;
    private $logger;
    private $configManager;

    public function __construct(FileAnalyzer $fileAnalyzer, FileManager $fileManager, ContentManager $contentManager, Tools $tools, Logger $logger, ConfigManager $configManager)
    {
        $this->fileAnalyzer = $fileAnalyzer;
        $this->fileManager = $fileManager;
        $this->contentManager = $contentManager;
        $this->tools = $tools;
        $this->logger = $logger;
        $this->configManager = $configManager;
    }

    public function migrate(string $path): void
    {
        $files = $this->fileAnalyzer->analyze($path);
        $this->checkAppIsReady($files);

        $this->fileManager->moveFiles($files);

        $this->configManager->updateConfig($files);

        $this->contentManager->updateNamespaces($files);
        $this->contentManager->addMissingServices($files);

        $this->configManager->importBundles();
        $this->configManager->addXmlServicesToYamlConfig($files);
        $this->configManager->deleteXMLServices($files);
        $this->configManager->removeOldConfigs();

        $this->fileManager->cpIndex();
        $this->fileManager->cpConsole();
    }

    /**
     * @param FileAnalyzed[]
     */
    private function checkAppIsReady(array $files): void
    {
        $this->logger->writeln('<info>Check app is ready</info>');
        $this->checkXMLServices($files);
    }

    /**
     * @param FileAnalyzed[]
     */
    private function checkXMLServices($files): void
    {
        $this->logger->writeln('<info>Checking services.xml files</info>');

        $xmlFiles = $this->tools->getKind($files, FileAnalyzer::FILE_KIND_XML);
        $this->logger->startProgressBar(count($xmlFiles));

        foreach ($xmlFiles as $file) {
            $this->logger->advanceProgressBar();
            if (!preg_match('/services.xml$/', $file->originPath)) {
                continue;
            }

            try {
                $services = new \SimpleXMLElement(\file_get_contents($file->originPath));
            } catch (\Exception $e) {
                $this->logger->writeln("\n<error>Error: XML files must be deleted or uncommented before running the migration so we can update the services in config.</error>");
                exit();
            }
        }

        $this->logger->finishProgressBar();
    }
}
