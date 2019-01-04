<?php

namespace MigrationHelperSF4\Manager;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\StreamOutput;

class Logger
{
    private $isProgressBarDisabled = false;
    private $progressBar;
    private $logger;
    private $output;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->output = new StreamOutput(fopen('php://stdout', 'w'));

        if (!$this->disableProgressBar()) {
            $this->progressBar = new ProgressBar($this->output);
            $this->progressBar->setFormat('very_verbose');
        }
    }

    public function __call($method, $args)
    {
        return call_user_func_array([$this->logger, $method], $args);
    }

    public function startProgressBar(int $max = 0): void
    {
        if ($this->isProgressBarDisabled) {
            return;
        }

        $this->progressBar->start($max);
    }

    public function advanceProgressBar(): void
    {
        if ($this->isProgressBarDisabled) {
            return;
        }

        $this->progressBar->advance();
    }

    public function finishProgressBar(): void
    {
        if ($this->isProgressBarDisabled) {
            return;
        }

        $this->progressBar->finish();
        $this->writeln("");
    }

    public function writeln(string $str): void
    {
        $this->output->writeln($str);
    }

    private function disableProgressBar(): bool
    {
        for ($x = 2; isset($_SERVER['argv'][$x]); $x++) {
            if (str_replace('-', '', $_SERVER['argv'][$x]) == 'v') {
                $this->isProgressBarDisabled = true;

                return true;
            }
        }

        return false;
    }
}