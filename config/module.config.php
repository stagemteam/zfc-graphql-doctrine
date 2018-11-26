<?php

namespace Stagem\ZfcGraphQL;

use DateTime;
use GraphQL;
use Popov\ZfcPermission\Acl\Acl;
use Zend\ServiceManager\Factory\InvokableFactory;

return [
    // Allow full access to GraphQL Schema
    'acl' => [
        'guest' => [
            ['target' => 'graphql/admin-index', 'access' => Acl::ACCESS_TOTAL],
        ],
    ],

    'actions' => [
        'graphql' => __NAMESPACE__ . '\Action',
    ],

    'dependencies' => [
        'aliases' => [
            'GraphPluginManager' => Service\Plugin\GraphPluginManager::class,
        ],
        'invokables' => [],
        'factories' => [
            GraphQL\Doctrine\Types::class => Service\Factory\TypesFactory::class,
            Service\Plugin\GraphPluginManager::class => Service\Plugin\GraphPluginFactory::class,
        ],
    ],

    'graph_plugins' => [
        'aliases' => [
            DateTime::class => Type\DateTimeType::class
        ],
        //'invokables' => [],
        'factories' => [
            Type\DateTimeType::class => InvokableFactory::class
        ],
    ],

];