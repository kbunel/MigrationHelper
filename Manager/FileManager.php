<?php

namespace MigrationHelperSF4\Manager;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Filesystem\Filesystem;
use MigrationHelperSF4\Manager\FileAnalyzer;
use MigrationHelperSF4\Manager\Logger;
use MigrationHelperSF4\Manager\Tools;
use MigrationHelperSF4\Model\FileAnalyzed;

class FileManager
{
    public const SRC                = 'src';
    public const APP                = 'app';
    public const FEATURE            = 'features';
    public const CONFIG_FILES       = ['app/config/config.yml', 'app/config/config_test.yml'];

    private const ARCHITECTURE_PATH = __DIR__ . '/../Resources/config/architecture.yaml';

    private const INDEX_PATH        = __DIR__ . '/../Resources/files/index.php.txt';
    private const CONSOLE_PATH      = __DIR__ . '/../Resources/files/console.php.txt';

    private $architecture;
    private $logger;
    private $tools;
    private $fs;

    public function __construct(Filesystem $filesystem, Tools $tools, Logger $logger)
    {
        $this->architecture = Yaml::parseFile(self::ARCHITECTURE_PATH);
        $this->logger = $logger;
        $this->fs = $filesystem;
        $this->tools = $tools;
    }

    /**
     * @param FileAnalyzed[]
     */
    public function moveFiles(array &$files)
    {
        $this->logger->writeln('<info>Moving files</info>');
        $this->logger->startProgressBar(count($files));

        foreach ($files as $file) {
            $this->logger->advanceProgressBar();

            $this->moveFile($file);
        }

        $this->logger->finishProgressBar();
    }

    public function write(string $filePath, string $content): void
    {
        $status = file_exists($filePath) ? 'Updating' : 'Creating';
        $this->logger->info($status . ' content in ' . $filePath);

        $this->fs->dumpFile($filePath, $content);
    }

    public function parseTemplate(string $templatePath, array $parameters): string
    {
        ob_start();
        extract($parameters, EXTR_SKIP);
        include $templatePath;

        return ob_get_clean();
    }

    public function remove(FileAnalyzed &$file): void
    {
        $path = $file->newPath ?? $file->originPath;

        $this->logger->info('deleted: ' . $path);
        $this->fs->remove($path);
        $file->isDeleted = true;
    }

    public function cpIndex(): void
    {
        $this->logger->writeln('<info>Creating index.php in public/</info>');
        shell_exec('cp ' . self::INDEX_PATH .' public/index.php');
    }

    public function cpConsole(): void
    {
        $this->logger->writeln('<info>Replacing bin/console/</info>');
        shell_exec('cp ' . self::CONSOLE_PATH .' bin/console');
    }

    private function moveFile(FileAnalyzed &$file): void
    {
        if (empty($this->architecture[$file->kind])) {
            return;
        }

        $file->newPath = $this->getNewPath($file);
        $file->newBundleNamePath = $this->tools->getBundleNameFromPath($file->newPath);

        $this->logger->info('Renamed: ' . $file->originPath . ' -> ' . $file->newPath);
        $this->fs->mkdir(pathinfo($file->newPath)['dirname']);
        $this->fs->rename($file->originPath, $file->newPath);
    }

    private function getNewPath(FileAnalyzed $file): string
    {
        if ($file->kind == FileAnalyzer::FILE_KIND_ROUTING) {
            preg_match('/[a-zA-Z0-9]+Bundle/', pathInfo($file->originPath)['dirname'], $bundleName);
            $filename = lcfirst(str_replace('Bundle', '', $bundleName[0]));

            return $this->architecture[$file->kind] . DIRECTORY_SEPARATOR . $filename . '.yaml';
        }

        $pathInfo = pathInfo($file->originPath);
        $folder = DIRECTORY_SEPARATOR . $this->getFolderPath($file);
        $targetFolder = $this->architecture[$file->kind] . $folder;
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';

        return $targetFolder . DIRECTORY_SEPARATOR . $pathInfo['filename'] . $extension;
    }

    private function getFolderPath(FileAnalyzed $file): string
    {
        $foldersInArchitecturePath = explode('/', $this->architecture[$file->kind]);
        $foldersRequiredInNamespace = explode('/', $file->originPath);
        $pathInfo = pathInfo($file->originPath);

        $folders = [];
        $bundlePassed = false;
        foreach ($foldersRequiredInNamespace as $folder) {
            if (preg_match('/[a-zA-Z0-9_]+Bundle/', $folder)) {
                $bundlePassed = true;

                continue;
            }

            if (!$bundlePassed) {
                continue;
            }

            $found = false;
            foreach ($foldersInArchitecturePath as $f) {
                if ($f == $folder) {
                    $found = true;
                    break;
                }
            }

            if ($found) {
                continue;
            }

            $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            if ($folder == $pathInfo['filename'] . $extension) {
                continue;
            }

            $folders[] = $folder;
        }

        $folders = count($folders) > 0 ?  DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $folders) : '';

        if ($file->kind == FileAnalyzer::FILE_KIND_PUBLIC) {
            return $folders;
        }

        if (preg_match('/[a-zA-Z0-9_]+Bundle/', $file->originPath, $bundleName)) {
            return str_replace('Bundle', '', $bundleName[0]) . $folders;
        }

        return $folders;
    }
}
