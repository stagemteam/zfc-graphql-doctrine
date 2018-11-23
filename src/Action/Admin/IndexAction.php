<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2018 Serhii Popov
 * This source file is subject to The MIT License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 *
 * @category Stagem
 * @package Stagem_ZfcGraphQL
 * @author Serhii Popov <popow.serhii@gmail.com>
 * @license https://opensource.org/licenses/MIT The MIT License (MIT)
 */

namespace Stagem\ZfcGraphQL\Action\Admin;

use Doctrine\ORM\EntityManager;
use Popov\ZfcUser\Model\User;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// @todo wait until they will start to use Psr in codebase @see https://github.com/zendframework/zend-mvc/blob/master/src/MiddlewareListener.php#L11
//use Psr\Http\Server\MiddlewareInterface;
//use Psr\Http\Server\RequestHandlerInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Interop\Http\Server\RequestHandlerInterface;

use Fig\Http\Message\RequestMethodInterface;
use Stagem\Product\GraphQL\Type\RankTrackingType;
use Stagem\Product\Model\Product;
use Stagem\Product\Service\HistoryChartService;
use Zend\Diactoros\Response\EmptyResponse;
use Zend\ServiceManager\ServiceManager;

use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Server\StandardServer;
use GraphQL\Doctrine\DefaultFieldResolver;
use GraphQL\Doctrine\Types;
use Stagem\Amazon\Model\Marketplace;

class IndexAction implements MiddlewareInterface, RequestMethodInterface
{
    /**
     * @var ServiceManager
     */
    protected $container;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    public function __construct(ContainerInterface $container, EntityManager $entityManager)
    {
        $this->container = $container;
        $this->entityManager = $entityManager;

        /** @var \Doctrine\ORM\Configuration $doctrineConfig */
        // @todo remove when will be fixed @see https://github.com/Ecodev/graphql-doctrine/issues/21#issuecomment-432064584
        $doctrineConfig = $this->container->get('doctrine.configuration.orm_default');
        $doctrineConfig->getMetadataDriverImpl()
            ->setDefaultDriver(new \Doctrine\ORM\Mapping\Driver\AnnotationDriver(
                new \Doctrine\Common\Annotations\AnnotationReader()
            ));
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        #$this->container->setAllowOverride(true);
        #$this->container->setInvokableClass(DateTime::class, DateTimeType::class);
        #$this->container->setAllowOverride(false);

        // Configure the type registry
        $types = new Types($this->entityManager, $this->container);

        // Configure default field resolver to be able to use getters
        GraphQL::setDefaultFieldResolver(new DefaultFieldResolver());

        try {
            $queryType = new ObjectType([
                'name' => 'query', // @todo Try change to Query
                'fields' => [

                    'rankTracking' => [
                        'type' => Type::listOf($this->container->get(RankTrackingType::class)),
                        'description' => 'Returns user by id (in range of 1-5)',
                        'args' => [
                            'productIds' => Type::nonNull(Type::listOf(Type::nonNull(Type::id()))),
                            //'productIds' => Type::nonNull(Type::id()),
                            //'lastDays' => Type::nonNull(Type::int()),
                            'startedAt' => $this->container->get(\Stagem\Product\GraphQL\Type\DateTimeType::class),
                            'endedAt' => $this->container->get(\Stagem\Product\GraphQL\Type\DateTimeType::class),
                        ],
                        'resolve' => function ($root, $args) use ($types) {

                            /** @var HistoryChartService $historyChartService */
                            $historyChartService = $this->container->get(HistoryChartService::class);

                            $product = $this->entityManager->find(Product::class, $args['productIds'][0]);
                            $marketplace = $this->entityManager->find(Marketplace::class, 1);

                            $result = $historyChartService->prepareChartData($product, $marketplace, ['startedAt' => $args['startedAt'], 'endedAt' => $args['endedAt']], 1);

                            return $result;
                        },
                    ],

                    'marketplace' => [
                        'type' => $types->getOutput(Marketplace::class), // Use automated ObjectType for output
                        'description' => 'Returns marketplace by id (in range of 1-6)',
                        'args' => [
                            'id' => Type::nonNull(Type::id())
                        ],
                        'resolve' => function ($root, $args) use ($types) {
                            $queryBuilder = $types->createFilteredQueryBuilder(Marketplace::class, $args['filter'] ?? [], $args['sorting'] ?? []);

                            $result = $queryBuilder->getQuery()->getArrayResult();

                            return $result;
                        },
                    ],
                    'marketplaces' => [
                        'type' => Type::listOf($types->getOutput(Marketplace::class)), // Use automated ObjectType for output
                        'args' => [
                            [
                                'name' => 'filter',
                                'type' => $types->getFilter(Marketplace::class), // Use automated filtering options
                            ],
                            [
                                'name' => 'sorting',
                                'type' => $types->getSorting(Marketplace::class), // Use automated sorting options
                            ],
                        ],
                        'resolve' => function ($root, $args) use ($types) {
                            $queryBuilder = $types->createFilteredQueryBuilder(Marketplace::class, $args['filter'] ?? [], $args['sorting'] ?? []);

                            $result = $queryBuilder->getQuery()->getArrayResult();

                            return $result;
                        },
                    ],

                    'user' => [
                        'type' => $types->getOutput(User::class), // Use automated ObjectType for output
                        'description' => 'Returns user by id',
                        'args' => [
                            'id' => Type::nonNull(Type::id())
                        ],
                        'resolve' => function ($root, $args) use ($types) {
                            $queryBuilder = $types->createFilteredQueryBuilder(Marketplace::class, $args['filter'] ?? [], $args['sorting'] ?? []);

                            $result = $queryBuilder->getQuery()->getArrayResult();

                            return $result;
                        },
                    ],
                ],
                'resolveField' => function($val, $args, $context, ResolveInfo $info) {
                    return $this->{$info->fieldName}($val, $args, $context, $info);
                }
            ]);

            $mutationType = new ObjectType([
                'name' => 'mutation',
                'fields' => [
                    'createMarketplace' => [
                        'type' => Type::nonNull($types->getOutput(Marketplace::class)),
                        'args' => [
                            'input' => Type::nonNull($types->getInput(Marketplace::class)), // Use automated InputObjectType for input
                        ],
                        'resolve' => function ($root, $args): void {
                            // create new post and flush...
                        },
                    ],
                    /*'updatePost' => [
                        'type' => Type::nonNull($types->getOutput(Marketplace::class)),
                        'args' => [
                            'id' => Type::nonNull(Type::id()), // Use standard API when needed
                            'input' => $types->getPartialInput(Post::class),  // Use automated InputObjectType for partial input for updates
                        ],
                        'resolve' => function ($root, $args): void {
                            // update existing post and flush...
                        },
                    ],*/
                    /*'login' => [
                        'type' => Type::nonNull($types->getOutput(Marketplace::class)),
                        'args' => [
                            'id' => Type::nonNull(Type::id()), // Use standard API when needed
                            'input' => $types->getPartialInput(Post::class),  // Use automated InputObjectType for partial input for updates
                        ],
                        'resolve' => function ($root, $args): void {
                            // update existing post and flush...
                        },
                    ],*/
                ],
            ]);

            // See docs on schema options:
            // http://webonyx.github.io/graphql-php/type-system/schema/#configuration-options
            $schema = new Schema([
                'query' => $queryType,
                'mutation' => $mutationType,
            ]);

            $schema->assertValid();

            // See docs on server options:
            // http://webonyx.github.io/graphql-php/executing-queries/#server-configuration-options
            $server = new StandardServer([
                'schema' => $schema
            ]);

            $server->handleRequest();
        } catch (\Exception $e) {
            StandardServer::send500Error($e);
        }

        return new EmptyResponse();
    }
}