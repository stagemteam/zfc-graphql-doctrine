<?php

namespace Stagem\ZfcGraphQL;

use DateTime;
use GraphQL;
use Stagem\GraphQL\Type;
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
            'json' => Type\JsonType::class,
            'array' => Type\IterableType::class,
            'iterable' => Type\IterableType::class,
            'email' => Type\EmailType::class,
            'date' => Type\DateType::class,
            'time' => Type\TimeType::class,
            'datetime' => Type\DateTimeType::class,
            DateTime::class => Type\DateTimeType::class,
            'SimpleObject' => Type\SimpleObjectType::class,
        ],
        //'invokables' => [],
        'factories' => [
            Type\JsonType::class => InvokableFactory::class,
            Type\IterableType::class => InvokableFactory::class,
            Type\EmailType::class => InvokableFactory::class,
            Type\DateType::class => InvokableFactory::class,
            Type\DateTimeType::class => InvokableFactory::class,
            Type\SimpleObjectType::class => InvokableFactory::class,
        ],
    ],

];