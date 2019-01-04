<?php

namespace MigrationHelperSF4\Analyzer;

use MigrationHelperSF4\Manager\Tools;

class ClassImplementationAnalyzer
{
    public const COMPILER                  = 'Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface';
    public const JMS_SUBSCRIBER            = 'JMS\Serializer\EventDispatcher\EventSubscriberInterface';
    public const USER_PROVIDER             = 'Symfony\Component\Security\Core\User\UserProviderInterface';
    public const SIMPLE_PRE_AUTHENTICATOR  = 'Symfony\Component\Security\Http\Authentication\SimplePreAuthenticatorInterface';
    public const SF_EVENT_SUBSCRIBER       = 'Symfony\Component\EventDispatcher\EventSubscriberInterface';
    public const CONFIGURATION             = 'Symfony\Component\Config\Definition\ConfigurationInterface';
    public const DOCTRINE_EVENT_SUBSCRIBER = 'Doctrine\Common\EventSubscriber';
    public const JMS_SUBSCRIBER_HANDLER    = 'JMS\Serializer\Handler\SubscribingHandlerInterface';
    public const JMS_EXCLUSION_STRATEGY    = 'JMS\Serializer\Exclusion\ExclusionStrategyInterface';
    public const DATA_TRANSFORMER          = 'Symfony\Component\Form\DataTransformerInterface';

    private $tools;

    public function __construct(Tools $tools)
    {
        $this->tools = $tools;
    }

    public function __call(string $method, array $args) {
        $interface = $this->tools->getConstanteFromIsMethod(self::class, $method);

        return $this->isFromInterface($args[0], $interface);
    }

    private function isFromInterface(string $filePath, string $interface): bool
    {
        if (is_null($reflector = $this->tools->getDeeperParentClass($filePath))
            && is_null($reflector = $this->tools->getReflectionClass($filePath))) {
                return false;
        }

        return $reflector->implementsInterface($interface);
    }
}