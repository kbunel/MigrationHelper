<?php

namespace MigrationHelperSF4\Manager;

use MigrationHelperSF4\Manager\FileManager;
use MigrationHelperSF4\Model\FileAnalyzed;

class Tools
{
    public function getNamespace(string $filePath): ?string
    {
        $lines = file($filePath, FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match('/^namespace.+/', $line)) {
                $ar = explode('/', $filePath);
                $fileName = preg_replace('/\..+$/', '', $ar[count($ar) - 1]);

                return str_replace([' ', 'namespace', ';'], '', $line) . '\\' . $fileName;
            }
        }

        return null;
    }

    public function getReflectionClass(string $filePath)
    {
        if (is_null($namespace = $this->getNamespace($filePath))) {
            return null;
        }

        return new \ReflectionClass($namespace);
    }

    public function camelCaseToUnderscore(string $str): string
    {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $str, $matches);
        $ret = $matches[0];

        foreach ($ret as &$match) {
          $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }

        return implode('_', $ret);
    }

    public function getDeeperParentClass(string $filePath)
    {
        if (is_null($reflector = $this->getReflectionClass($filePath))) {
            return null;
        }

        while ($reflector->getParentClass()) {
            $reflector = $reflector->getParentClass();
        }

        return $reflector;
    }

    public function getConstanteFromIsMethod(string $class, string $method): string
    {
        return constant($class . '::' . strtoupper(preg_replace('/^_/', '', preg_replace('/^is/', '', $this->camelCaseToUnderscore($method)))));
    }

    public function getBundleNameFromPath(string $filePath): ?string
    {
        $pathInfo = pathinfo($filePath);
        if (!isset($pathInfo['extension']) || $pathInfo['extension'] != 'php' || !preg_match('/Bundle/', $filePath)) {
            return null;
        }

        $folders = explode('/', $filePath);
        $bundleNamePath = '@';
        $rootPassed = false;
        $bundlePassed = false;
        foreach ($folders as $folder) {
            if ($folder == FileManager::SRC) {
                $rootPassed = true;

                continue;
            }

            if (!$rootPassed) {
                continue;
            }

            if ($bundlePassed) {
                $bundleNamePath .= DIRECTORY_SEPARATOR;
            }

            $bundleNamePath .= $folder;

            if (preg_match('/[a-zA-Z0-9]Bundle/', $folder)) {
                $bundlePassed = true;
            }
        }

        return $bundleNamePath;
    }

    public function getShortBundlePath(FileAnalyzed $file): string
    {
        $ar = explode('/', $file->originBundleNamePath);
        $pathInfo = pathinfo($file->originPath);

        $bundleName = str_replace('@', '', $ar[0]);
        $entityName = $pathInfo['filename'];

        return $bundleName . ':' . $entityName;
    }

    /**
     * @param FileAnalyzed[]
     *
     * @return FileAnalyzed[]
     */
    public function getKind(array $files, string $kind): array
    {
        $f = [];
        foreach ($files as $file) {
            if ($file->kind == $kind) {
                $f[] = $file;
            }
        }

        return $f;
    }
}
