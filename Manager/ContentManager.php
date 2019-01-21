<?php

namespace MigrationHelperSF4\Manager;

use Symfony\Component\Yaml\Yaml;
use FileAnalyzer\Services\FileAnalyzer;
use FileAnalyzer\Model\FileAnalyzed;
use FileAnalyzer\Services\Tools;
use FileAnalyzer\Services\Logger;
use MigrationHelperSF4\Manager\FileManager;

class ContentManager
{
    private $fileManager;
    private $logger;
    private $tools;

    public function __construct(Tools $tools, FileManager $fileManager, Logger $logger)
    {
        $this->fileManager = $fileManager;
        $this->logger = $logger;
        $this->tools = $tools;
    }

    /**
     * @param FileAnalyzed[]
     */
    public function updateNamespaces(array &$files): void
    {
        $this->updateNamespacesInFiles($files);

        $this->logger->writeln('<info>Updating namespaces called in src files</info>');
        $this->logger->startProgressBar(count($files));
        $this->updateNamespacesCalled($files);
        $this->logger->finishProgressBar();

        $this->logger->writeln('<info>Updating namespaces called in feature files</info>');
        $this->logger->startProgressBar(count($files));
        $this->updateNamespacesCalled($files, FileManager::FEATURE);
        $this->logger->finishProgressBar();

        $this->logger->writeln('<info>Updating namespaces called in app files</info>');
        $this->logger->startProgressBar(count($files));
        $this->updateNamespacesCalled($files, FileManager::APP);
        $this->logger->finishProgressBar();
    }

    /**
     * @param FileAnalyzed[]
     */
    public function addMissingServices(array $filesAnalyzed): void
    {
        $this->logger->writeln('<info>Importing required classes in files</info>');
        $this->logger->startProgressBar();

        $this->addMissingServicesToFiles($filesAnalyzed);

        $this->logger->finishProgressBar();
    }

    private function addMissingServicesToFiles(array $filesAnalyzed, string $path = FileManager::SRC): void
    {
        $files = scandir($path);
        foreach ($files as $file) {
            if (in_array($file, ['.', '..'])) {
                continue;
            }

            $filePath = $path . DIRECTORY_SEPARATOR . $file;
            if (is_dir($filePath)) {
                $this->addMissingServicesToFiles($filesAnalyzed, $filePath);

                continue;
            }

            if (is_file($filePath)) {
                $pathInfo = pathInfo($filePath);
                if (isset($pathInfo['extension']) && $pathInfo['extension'] == 'php') {
                    $this->addMissingServicesToFile($filesAnalyzed, $filePath);
                }
            }
        }
    }

    /**
     * @param filesAnalyzed[]
     */
    private function addMissingServicesToFile(array $files, string $filePath): void
    {
        $filename = pathinfo($filePath)['filename'];
        $lines = file($filePath, FILE_SKIP_EMPTY_LINES);
        $fileUpdated = false;

        foreach ($files as $file) {
            $ar = explode('\\', $file->newNamespace);
            $serviceName = $ar[count($ar) - 1];

            if ($serviceName == $filename || !$file->newNamespace) {
                continue;
            }

            $classPassed = false;
            foreach ($lines as $key => $line) {
                $line = trim($line);

                if (!$classPassed && preg_match('/' . $serviceName . ';/', $line)) {
                    break;
                }

                if (preg_match('/^(class|interface|trait)/', $line)) {
                    $classPassed = true;
                }

                if (preg_match_all('/' . $serviceName . '[^a-zA-Z0-9]/', $line, $matches)) {

                    preg_match_all('/[\'"][a-zA-Z0-9 \-\_\@]*' . $serviceName . '[a-zA-Z0-9 \-\_\@]*[\'"]/', $line, $matchesString);
                    preg_match_all('/' . preg_quote($file->newNamespace) . '/', $line, $matchesFullNamespace);
                    preg_match_all('/\\\\' . $serviceName . '/', $line, $matchesIsAnotherNamespace);
                    preg_match_all('/:' . $serviceName . '/', $line, $matchesACall);
                    preg_match_all('/[a-zA-Z0-9\-\_]' . $serviceName . '/', $line, $matchesInAntoherWord);

                    if (count($matchesFullNamespace[0]) + count($matchesString[0]) + count($matchesIsAnotherNamespace[0]) + count($matchesACall[0]) + count($matchesInAntoherWord[0]) >= count($matches[0])) {
                        continue;
                    }

                    $fileUpdated = true;
                    $lines = $this->addServiceInFileLines($file, $lines);
                    $this->logger->advanceProgressBar();
                    break;
                }
            }
        }

        if ($fileUpdated) {
            $this->fileManager->write($filePath, implode($lines));
        }
    }

    private function addServiceInFileLines(FileAnalyzed $file, array $lines): array
    {
        $usedPassed = false;
        foreach ($lines as $key => $line) {
            $line = trim($line);

            if (preg_match('/^use/', $line)) {
                $usedPassed = true;

                continue;
            }

            if ($usedPassed) {
                array_splice($lines, $key, 0, ["use " . $file->newNamespace . ";\n"]);

                return $lines;
            }

            if (preg_match('/^(class|interface|trait)/', $line)) {
                for ($x = 0; isset($lines[$x]); $x++) {
                    if (preg_match('/^namespace/', trim($lines[$x]))) {
                        array_splice($lines, $x + 1, 0, ["\n"]);
                        array_splice($lines, $x + 2, 0, ["use " . $file->newNamespace . ";\n"]);

                        return $lines;
                    }
                }
            }
        }

        throw new \LogicException('Should have add the service in file but didn\'t, something went wrong');
    }

    /**
     * @param FileAnalyzed[]
     */
    private function updateNamespacesCalled(array $filesAnalyzed, string $path = FileManager::SRC): void
    {
        $files = scandir($path);
        foreach ($files as $file) {
            if (in_array($file, ['.', '..'])) {
                continue;
            }

            $filePath = $path . DIRECTORY_SEPARATOR . $file;
            if (is_dir($filePath)) {
                $this->updateNamespacesCalled($filesAnalyzed, $filePath);

                continue;
            }

            if (is_file($filePath)) {
                $this->findAndUpdateNamespaces($filesAnalyzed, $filePath);
            }
        }
    }

    private function findAndUpdateNamespaces(array $files, $filePath): void
    {
        $lines = file($filePath, FILE_SKIP_EMPTY_LINES);
        $countA = 0;
        $countB = 0;

        foreach ($files as $file) {
            if (!$file->originNamespace || !$file->newPath) {
                continue;
            }

            $regexs = [
                '/use *' . preg_quote($file->originNamespace) . ' *;/' => 'use ' . $file->newNamespace . ';',
                '/\'' . preg_quote($file->originNamespace) . '\'/' => '\'' . $file->newNamespace . '\'',
                '/"' . preg_quote($file->originNamespace) . '"/' => '"' . $file->newNamespace . '"',
                '/\'\\\\' . preg_quote($file->originNamespace) . '\'/' => '\'\\\\' . $file->newNamespace . '\'',
                '/"\\\\' . preg_quote($file->originNamespace) . '"/' => '"\\\\' . $file->newNamespace . '"',
                '/ \\\\' . preg_quote($file->originNamespace) . ' $/' => '"\\\\' . $file->newNamespace . ' ',
                '/' . preg_quote($file->originNamespace) . ':/' => $file->newNamespace . ':',
                '/' . preg_quote($file->originNamespace) . ' /' => $file->newNamespace . ' ',
                '/' . preg_quote($file->originNamespace) . '$/' => $file->newNamespace,
            ];

            if ($file->kind == FileAnalyzer::FILE_KIND_ENTITY) {
                $regexs += [
                    '/' . $file->shortBundleEntityPath . '/' => $file->newNamespace
                ];
            }

            foreach ($lines as $key => $line) {
                foreach ($regexs as $reg => $replace) {
                    $lines[$key] = preg_replace(
                        $reg,
                        $replace,
                        $line,
                        -1,
                        $count
                    );

                    if ($count) {
                        $countA += $count;

                        break;
                    }
                }
            }

            if ($file->newBundleNamePath) {
                $lines = preg_replace(
                    '/[\'"] *' . preg_quote($file->originBundleNamePath) . ' *[\'"]/',
                    '"' . $file->newBundleNamePath . '"',
                    $lines,
                    -1,
                    $count
                );

                $countB += $count;
            }
        }

        if ($countA || $countB) {
            $this->fileManager->write($filePath, implode($lines));
        }

        $this->logger->advanceProgressBar($countA + $countB);
    }

    private function updateNamespacesInFiles(array &$files): void
    {
        $this->logger->writeln('<info>Updating self namespaces in files</info>');
        $this->logger->startProgressBar(count($files));

        foreach ($files as $file) {
            $this->logger->advanceProgressBar();

            $pathInfo = pathinfo($file->newPath);
            if (!isset($pathInfo['extension']) || $pathInfo['extension'] != 'php') {
                continue;
            }

            $this->updateNamespace($file);
        }

        $this->logger->finishProgressBar();
    }

    private function updateNamespace(FileAnalyzed &$file)
    {
        $fileNamespace = $this->getNewNamespace($file, FILE_SKIP_EMPTY_LINES);

        $lines = file($file->newPath);
        foreach ($lines as $key => $line) {
            $line = trim($line);

            if (preg_match('/^namespace/', $line)) {
                $lines[$key] = "namespace " . $fileNamespace . ";\n";
                break;
            }
        }

        $file->newNamespace = $fileNamespace . '\\' . pathinfo($file->newPath)['filename'];
        $this->fileManager->write($file->newPath, implode($lines));
    }

    private function getNewNamespace(FileAnalyzed $file): string
    {
        $pathInfo = pathInfo($file->newPath);

        return str_replace('src', 'App', implode('\\', explode('/', $pathInfo['dirname'])));
    }
}
