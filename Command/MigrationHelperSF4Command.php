<?php

namespace MigrationHelperSF4\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MigrationHelperSF4\Manager\MigrationService;

class MigrationHelperSF4Command extends ContainerAwareCommand
{
    protected static $defaultName = 'kbunel:migrate:sf4';

    private $migrationService;

    public function __construct(MigrationService $migrationService)
    {
        $this->migrationService = $migrationService;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName(self::$defaultName)
            ->setDescription('Migrate from sf3 to sf4 architecture')
            ->addArgument('path', InputArgument::OPTIONAL)
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->migrationService->migrate($input->getArgument('path') ?? 'src');
    }
}

