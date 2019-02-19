<?php

namespace MigrationHelperSF4\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MigrationHelperSF4\Services\MigrationHelperSF4;

class ServicesFromXMLToYamlCommand extends ContainerAwareCommand
{
    protected static $defaultName = 'kbunel:services:xmlToYaml';

    private $fileAnalyzer;
    private $configManager;

    public function __construct(FileAnalyzer $fileAnalyzer, ConfigManager $configManager)
    {
        $this->fileAnalyzer = $fileAnalyzer;
        $this->configManager = $configManager;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName(self::$defaultName)
            ->setDescription('Update app/config/config.yml from all services.xml in path if not in config yet')
            ->addArgument('path', InputArgument::OPTIONAL)
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $files = $this->fileAnalyzer->analyze($input->getArgument('path') ?? 'src');

        $this->configManager->addXmlServicesToYamlConfig($files);
    }
}
