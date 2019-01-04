<?php

namespace MigrationHelperSF4\Analyzer;

use MigrationHelperSF4\Manager\Tools;

class ClassExtensionAnalyzer
{
    public const CONTROLLER           = 'Symfony\Bundle\FrameworkBundle\Controller\Controller';
    public const FORM_TYPE            = 'Symfony\Component\Form\AbstractType';
    public const REPOSITORY           = 'Doctrine\ORM\EntityRepository';
    public const BUNDLE               = 'Symfony\Component\HttpKernel\Bundle\Bundle';
    public const CONSTRAINT_VALIDATOR = 'Symfony\Component\Validator\ConstraintValidator';
    public const CONSTRAINT           = 'Symfony\Component\Validator\Constraint';
    public const VOTER                = 'Symfony\Component\Security\Core\Authorization\Voter\Voter';
    public const EXTENSION            = 'Symfony\Component\DependencyInjection\Extension\Extension';
    public const UNIT_TEST            = 'PHPUnit_Framework_Assert';
    public const ENTITY_MANAGER       = 'Doctrine\ORM\EntityManager';
    public const JMS_CONTEXT          = 'JMS\Serializer\Context';
    public const SENSIO_DOCTRINE_PARAM_CONVERTER = 'Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\DoctrineParamConverter';
    public const DOCTRINE_PAGINATOR   = 'Doctrine\ORM\Tools\Pagination\Paginator';
    public const SQL_FILTER           = 'Doctrine\ORM\Query\Filter\SQLFilter';
    public const TWIG_EXTENSION       = 'Twig_Extension';
    public const EXCEPTION            = 'Exception';
    public const EVENT                = 'Symfony\Component\EventDispatcher\Event';
    public const DOCTRINE_NODE        = 'Doctrine\ORM\Query\AST\Node';
    public const COMMAND              = 'Symfony\Component\Console\Command\Command';
    public const TEST_CASE            = 'PHPUnit_Framework_TestCase';

    private $tools;

    public function __construct(Tools $tools)
    {
        $this->tools = $tools;
    }

    public function __call(string $method, array $args)
    {
        $extend = $this->tools->getConstanteFromIsMethod(self::class, $method);

        return $this->isExtendedBy($args[0], $extend);
    }

    public function isExtendedBy(string $filePath, $extend): bool
    {
        if (is_null($deeperParentClass = $this->tools->getDeeperParentClass($filePath))) {
            return false;
        }

        return $deeperParentClass->getName() == $extend;
    }
}