<?php

namespace MigrationHelperSF4\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MigrationHelperSF4\Services\MigrationHelperSF4;

class MigrationHelperSF4Command extends ContainerAwareCommand
{
    protected static $defaultName = 'kbunel:migrate:sf4';

    private $migrationHelperSF4;

    public function __construct(MigrationHelperSF4 $migrationHelperSF4)
    {
        $this->migrationHelperSF4 = $migrationHelperSF4;

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
        $this->migrationHelperSF4->migrate($input->getArgument('path') ?? 'src');
    }
}

