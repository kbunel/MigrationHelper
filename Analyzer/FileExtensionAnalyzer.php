<?php

namespace MigrationHelperSF4\Analyzer;

class FileExtensionAnalyzer
{
    private const YAML = ['yaml', 'yml'];
    private const TWIG = 'twig';
    private const XML  = 'xml';

    public function isTwig(string $filePath): bool
    {
        return isset(pathinfo($filePath)['extension']) && pathinfo($filePath)['extension'] == self::TWIG;
    }

    public function isYaml(string $filePath): bool
    {
        return isset(pathinfo($filePath)['extension']) && in_array(pathinfo($filePath)['extension'], self::YAML);
    }

    public function isXml(string $filePath): bool
    {
        return isset(pathinfo($filePath)['extension']) && pathinfo($filePath)['extension'] == self::XML;
    }
}