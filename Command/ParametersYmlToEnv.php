<?php

namespace Staffmatch\CoreBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Dotenv\Dotenv;

class checkParametersCommand extends Command
{
    protected static $defaultName = 'check:parameters:env';

    private const SKIP = [];

    protected function configure()
    {
        $this
            ->setDescription('TMP')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $output->writeln('<info>Starting...</info>');
        $io->newLine();

        (new Dotenv())->load('.env.dist');
        $parameters = Yaml::parseFile('./app/config/parameters.yml.dist');

        foreach ($parameters['parameters'] as $key => $parameter) {
            if ($key == 'parameters') {
                continue;
            }

            if (!is_string($key)) {
                $output->writeln('Skipping' . $parameters['parameters'][$key]);
                continue;
            }

            if (in_array($key, self::SKIP)) {
                continue;
            }

            if (!isset($_ENV[strtoupper($key)])) {
                throw new \Exception('Missing env declaration -> ' . $key);
            }

            if ($_ENV[strtoupper($key)] == 'null' && $parameter == ''
            || $_ENV[strtoupper($key)] == 'NULL' && $parameter == '') {
                continue;
            }

            if ($_ENV[strtoupper($key)] != $parameter) {
                throw new \Exception('Error, values are not the same -> ' . $key . ':' . $parameter . ' | ' . strtoupper($key) . '=' . $_ENV[strtoupper($key)]);
            }
        }

        $io->success('DONE!');

    }
}
