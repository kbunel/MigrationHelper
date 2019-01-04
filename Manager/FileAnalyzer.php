<?php

namespace MigrationHelperSF4\Manager;

use MigrationHelperSF4\Analyzer\DiversAnalyzer;
use MigrationHelperSF4\Analyzer\FileExtensionAnalyzer;
use MigrationHelperSF4\Analyzer\ClassImplementationAnalyzer;
use MigrationHelperSF4\Analyzer\ClassExtensionAnalyzer;
use MigrationHelperSF4\Analyzer\ServiceAnalyzer;
use MigrationHelperSF4\Manager\Tools;
use MigrationHelperSF4\Manager\Logger;
use MigrationHelperSF4\Model\FileAnalyzed;

class FileAnalyzer
{
    public const FILE_KIND_ENTITY                           = 'entity';
    public const FILE_KIND_CONTROLLER                       = 'controller';
    public const FILE_KIND_FORM_TYPE                        = 'form.type';
    public const FILE_KIND_COMPILER                         = 'compiler';
    public const FILE_KIND_REPOSITORY                       = 'repository';

    public const FILE_KIND_CONSTRAINT_VALIDATOR             = 'validator.constraint_validator';
    public const FILE_KIND_CONSTRAINT                       = 'validator.constraint';

    public const FILE_KIND_UNKNOWN_FROM_CLASS               = 'service';
    public const FILE_KIND_VOTER                            = 'voter';
    public const FILE_KIND_USER_PROVIDER                    = 'security.user_provider';
    public const FILE_KIND_SIMPLE_PRE_AUTHENTICATOR         = 'security.simple_pre_authenticator';
    public const FILE_KIND_UNIT_TEST                        = 'web_test_case';
    public const FILE_KIND_ENTITY_MANAGER                   = 'entity_manager';
    public const FILE_KIND_JMS_CONTEXT                      = 'serializer_context';

    public const FILE_KIND_JMS_SUBSCRIBER_HANDLER           = 'jms_serializer.subscribing_handler';
    public const FILE_KIND_JMS_SUBSCRIBER                   = 'jms_serializer.event_subscriber';
    public const FILE_KIND_JMS_EXCLUSION_STRATEGY           = 'jms.exclusion_strategy';

    public const FILE_KIND_PUBLIC                           = 'public';
    public const FILE_KIND_SENSIO_DOCTRINE_PARAM_CONVERTER  = 'sensio_doctrine_param_converter';
    public const FILE_KIND_TRAIT                            = 'trait';
    public const FILE_KIND_INTERFACE                        = 'interface';
    public const FILE_KIND_DOCTRINE_PAGINATOR               = 'doctrine_paginator';
    public const FILE_KIND_DATA_TRANSFORMER                 = 'data_transformer';
    public const FILE_KIND_SQL_FILTER                       = 'sql_filter';
    public const FILE_KIND_TWIG_EXTENSION                   = 'twig_extension';
    public const FILE_KIND_EXCEPTION                        = 'exception';
    public const FILE_KIND_EVENT                            = 'event';
    public const FILE_KIND_DOCTRINE_NODE                    = 'doctrine_node';
    public const FILE_KIND_COMMAND                          = 'command';

    public const FILE_KIND_DOCTRINE_EVENT_LISTENER          = 'doctrine.event_listener';
    public const FILE_KIND_DOCTRINE_EVENT_SUBSCRIBER        = 'doctrine.event_subscriber';
    public const FILE_KIND_DOCTRINE_ORM_ENTITY_LISTENER     = 'doctrine.orm.entity_listener';
    public const FILE_KIND_KERNEL_EVENT_LISTENER            = 'kernel.event_listener';
    public const FILE_KIND_KERNEL_EVENT_SUBSCRIBER          = 'kernel.event_subscriber';

    public const FILE_KIND_ROUTING                          = 'routing';

    public const FILE_KIND_BUNDLE                           = 'bundle';
    public const FILE_KIND_EXTENSION                        = 'dependency_injection.extension';
    public const FILE_KIND_CONFIGURATION                    = 'dependency_injection.configuration';
    public const FILE_KIND_TEST_CASE                        = 'test_case';
    public const FILE_KIND_HIDDEN_FILE                      = 'hidden_file';

    public const FILE_KIND_YAML                             = 'yaml';
    public const FILE_KIND_TWIG                             = 'twig';
    public const FILE_KIND_XML                              = 'xml';

    public const FILE_KIND_UNKNOWN                          = 'unknown_kind';

    private $classImplementationAnalyzer;
    private $classExtensionAnalyzer;
    private $fileExtensionAnalyzer;
    private $serviceAnalyzer;
    private $diversAnalyzer;
    private $files = [];
    private $logger;
    private $tools;

    public function __construct(Tools $tools, DiversAnalyzer $diversAnalyzer, FileExtensionAnalyzer $fileExtensionAnalyzer, ClassImplementationAnalyzer $classImplementationAnalyzer, ClassExtensionAnalyzer $classExtensionAnalyzer, Logger $logger, ServiceAnalyzer $serviceAnalyzer)
    {
        $this->classImplementationAnalyzer = $classImplementationAnalyzer;
        $this->classExtensionAnalyzer = $classExtensionAnalyzer;
        $this->fileExtensionAnalyzer = $fileExtensionAnalyzer;
        $this->serviceAnalyzer = $serviceAnalyzer;
        $this->diversAnalyzer = $diversAnalyzer;
        $this->logger = $logger;
        $this->tools = $tools;
    }

    /**
     * @return FileAnalyzed[]
     */
    public function analyze(string $path): array
    {
        $this->logger->writeln('<info>Getting required informations from ' . $path . ' files</info>');
        $this->logger->startProgressBar();
        $this->getFilesInformations($path);
        $this->logger->finishProgressBar();

        return $this->files;
    }

    public function getServices(string $filePath): array
    {
        $reflector = new \ReflectionClass($this->tools->getNamespace($filePath));
        $filePath = $reflector->getFileName();
        $servicesUsed = [];

        $lines = file($filePath, FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match('/^use.+/', $line)) {
                $servicesUsed[] = str_replace(['use ', ';'], '', $line);

                continue;
            }

            if (preg_match('/^class/', $line)) {
                return $servicesUsed;
            }
        }

        return $servicesUsed;
    }

    private function getFilesInformations(string $path): void
    {
        if (is_file($path)) {
            $this->addFile($path);

            return;
        }

        $files = scandir($path);
        foreach ($files as $file) {
            if (in_array($file, ['.', '..'])) {
                continue;
            }

            $filePath = $path . DIRECTORY_SEPARATOR . $file;
            if (is_dir($filePath)) {
                $this->getFilesInformations($filePath);

                continue;
            }

            if (is_file($filePath)) {
                $this->addFile($filePath);
            }
        }
    }

    private function addFile(string $filePath): void
    {
        $file = new FileAnalyzed();

        $file->kind = $this->getKind($filePath);
        $file->originPath = $filePath;
        $file->originNamespace = $this->tools->getNamespace($file->originPath);
        $file->originBundleNamePath = $this->tools->getBundleNameFromPath($filePath);

        if ($file->kind == self::FILE_KIND_ENTITY) {
            $file->shortBundleEntityPath = $this->tools->getShortBundlePath($file);
        }

        $this->files[] = $file;
        $this->logger->advanceProgressBar();
    }

    private function getKind(string $filePath): string
    {
        switch (true) {
            case $this->diversAnalyzer->isEntity($filePath):
                return self::FILE_KIND_ENTITY;
            case $this->diversAnalyzer->isPublic($filePath):
                return self::FILE_KIND_PUBLIC;
            case $this->diversAnalyzer->isTrait($filePath):
                return self::FILE_KIND_TRAIT;
            case $this->diversAnalyzer->isInterface($filePath):
                return self::FILE_KIND_INTERFACE;
            case $this->diversAnalyzer->isHiddenFile($filePath):
                return self::FILE_KIND_HIDDEN_FILE;
            case $this->diversAnalyzer->isRoutingFile($filePath):
                return self::FILE_KIND_ROUTING;
            case $this->serviceAnalyzer->isDoctrineEventListener($filePath):
                return self::FILE_KIND_DOCTRINE_EVENT_LISTENER;
            case $this->serviceAnalyzer->isDoctrineOrmEntityListener($filePath):
                return self::FILE_KIND_DOCTRINE_ORM_ENTITY_LISTENER;
            case $this->serviceAnalyzer->isKernelEventListener($filePath):
                return self::FILE_KIND_KERNEL_EVENT_LISTENER;
            case $this->serviceAnalyzer->isKernelEventSubscriber($filePath):
                return self::FILE_KIND_KERNEL_EVENT_SUBSCRIBER;
            case $this->serviceAnalyzer->isDoctrineEventSubscriber($filePath):
                return self::FILE_KIND_DOCTRINE_EVENT_SUBSCRIBER;
            case $this->serviceAnalyzer->isJmsSubscribingHandler($filePath):
                return self::FILE_KIND_JMS_SUBSCRIBER_HANDLER;
            case $this->serviceAnalyzer->isValidatorConstraintValidator($filePath):
                return self::FILE_KIND_CONSTRAINT_VALIDATOR;
            case $this->serviceAnalyzer->isJmsEventSubscriber($filePath):
                return self::FILE_KIND_JMS_SUBSCRIBER;
            case $this->classExtensionAnalyzer->isTestCase($filePath):
                return self::FILE_KIND_TEST_CASE;
            case $this->classExtensionAnalyzer->isCommand($filePath):
                return self::FILE_KIND_COMMAND;
            case $this->classExtensionAnalyzer->isDoctrineNode($filePath):
                return self::FILE_KIND_DOCTRINE_NODE;
            case $this->classExtensionAnalyzer->isException($filePath):
                return self::FILE_KIND_EXCEPTION;
            case $this->classExtensionAnalyzer->isTwigExtension($filePath):
                return self::FILE_KIND_TWIG_EXTENSION;
            case $this->classExtensionAnalyzer->isController($filePath):
                return self::FILE_KIND_CONTROLLER;
            case $this->classExtensionAnalyzer->isFormType($filePath):
                return self::FILE_KIND_FORM_TYPE;
            case $this->classExtensionAnalyzer->isRepository($filePath):
                return self::FILE_KIND_REPOSITORY;
            case $this->classExtensionAnalyzer->isBundle($filePath):
                return self::FILE_KIND_BUNDLE;
            case $this->classExtensionAnalyzer->isConstraintValidator($filePath):
                return self::FILE_KIND_CONSTRAINT_VALIDATOR;
            case $this->classExtensionAnalyzer->isConstraint($filePath):
                return self::FILE_KIND_CONSTRAINT;
            case $this->classExtensionAnalyzer->isVoter($filePath):
                return self::FILE_KIND_VOTER;
            case $this->classExtensionAnalyzer->isExtension($filePath):
                return self::FILE_KIND_EXTENSION;
            case $this->classExtensionAnalyzer->isUnitTest($filePath):
                return self::FILE_KIND_UNIT_TEST;
            case $this->classExtensionAnalyzer->isEntityManager($filePath):
                return self::FILE_KIND_ENTITY_MANAGER;
            case $this->classExtensionAnalyzer->isJmsContext($filePath):
                return self::FILE_KIND_JMS_CONTEXT;
            case $this->classExtensionAnalyzer->isSensioDoctrineParamConverter($filePath):
                return self::FILE_KIND_SENSIO_DOCTRINE_PARAM_CONVERTER;
            case $this->classExtensionAnalyzer->isDoctrinePaginator($filePath):
                return self::FILE_KIND_DOCTRINE_PAGINATOR;
            case $this->classExtensionAnalyzer->isSqlFilter($filePath):
                return self::FILE_KIND_SQL_FILTER;
            case $this->classExtensionAnalyzer->isEvent($filePath):
                return self::FILE_KIND_EVENT;
            case $this->fileExtensionAnalyzer->isYaml($filePath):
                return self::FILE_KIND_YAML;
            case $this->fileExtensionAnalyzer->isTwig($filePath):
                return self::FILE_KIND_TWIG;
            case $this->fileExtensionAnalyzer->isXml($filePath):
                return self::FILE_KIND_XML;
            case $this->classImplementationAnalyzer->isCompiler($filePath):
                return self::FILE_KIND_COMPILER;
            case $this->classImplementationAnalyzer->isJmsSubscriber($filePath):
                return self::FILE_KIND_JMS_SUBSCRIBER;
            case $this->classImplementationAnalyzer->isUserProvider($filePath):
                return self::FILE_KIND_USER_PROVIDER;
            case $this->classImplementationAnalyzer->isSimplePreAuthenticator($filePath):
                return self::FILE_KIND_SIMPLE_PRE_AUTHENTICATOR;
            case $this->classImplementationAnalyzer->isSfEventSubscriber($filePath):
                return self::FILE_KIND_KERNEL_EVENT_SUBSCRIBER;
            case $this->classImplementationAnalyzer->isDoctrineEventSubscriber($filePath):
                return self::FILE_KIND_DOCTRINE_EVENT_SUBSCRIBER;
            case $this->classImplementationAnalyzer->isConfiguration($filePath):
                return self::FILE_KIND_CONFIGURATION;
            case $this->classImplementationAnalyzer->isJmsSubscriberHandler($filePath):
                return self::FILE_KIND_JMS_SUBSCRIBER_HANDLER;
            case $this->classImplementationAnalyzer->isJmsExclusionStrategy($filePath):
                return self::FILE_KIND_JMS_EXCLUSION_STRATEGY;
            case $this->classImplementationAnalyzer->isDataTransformer($filePath):
                return self::FILE_KIND_DATA_TRANSFORMER;
            case $this->diversAnalyzer->isUnknownFromClass($filePath):
                return self::FILE_KIND_UNKNOWN_FROM_CLASS;
            default:
                return self::FILE_KIND_UNKNOWN;
        }
    }
}
