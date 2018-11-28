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
use Popov\ZfcRole\Model\Role;
use Popov\ZfcUser\Helper\UserHelper;
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
use Stagem\Customer\Model\Customer;
use Stagem\Order\Model\MarketOrder;
use Stagem\Product\GraphQL\Type\MarketOrderType;
use Stagem\Product\GraphQL\Type\RankTrackingType;
use Stagem\Product\Model\Product;
use Stagem\Product\Service\HistoryChartService;
use Stagem\Review\Model\Review;
use Stagem\Shipment\Model\Shipment;
use Zend\Diactoros\Response\EmptyResponse;
use Zend\ServiceManager\ServiceManager;

use GraphQL\GraphQL;
use GraphQL\Type\Schema;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use GraphQL\Doctrine\DefaultFieldResolver;
use GraphQL\Doctrine\Types;
use Stagem\Amazon\Model\Marketplace;

use GraphQL\Examples\Blog\AppContext;

use Stagem\ZfcAction\Page\AbstractAction;
use Zend\Stdlib\Exception\InvalidArgumentException;

//class IndexAction implements MiddlewareInterface, RequestMethodInterface
class IndexAction extends AbstractAction
{
    /**
     * @var Types
     */
    protected $types;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    //public function __construct(ContainerInterface $container, EntityManager $entityManager)
    //public function __construct(\Stagem\ZfcGraphQL\Service\Plugin\GraphPluginManager $container, EntityManager $entityManager)
    public function __construct(Types $types, EntityManager $entityManager)
    {
        $this->types = $types;
        //$this->container = $container;
        $this->entityManager = $entityManager;

        //$entityManager->getConfiguration()

        /** @var \Doctrine\ORM\Configuration $doctrineConfig */
        // @todo remove when will be fixed @see https://github.com/Ecodev/graphql-doctrine/issues/21#issuecomment-432064584
        //$doctrineConfig = $this->container->get('doctrine.configuration.orm_default');
        $doctrineConfig = $entityManager->getConfiguration();
        $doctrineConfig->getMetadataDriverImpl()
            ->setDefaultDriver(new \Doctrine\ORM\Mapping\Driver\AnnotationDriver(
                new \Doctrine\Common\Annotations\AnnotationReader()
            ));
    }

    public function action(ServerRequestInterface $request)
    {
        // TODO: Implement action() method.
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        #$this->container->setAllowOverride(true);
        #$this->container->setInvokableClass(DateTime::class, DateTimeType::class);
        #$this->container->setAllowOverride(false);

        // Configure the type registry
        //$types = new Types($this->entityManager, $this->container);
        //$types = $this->types;

        //$date = $types->get(\DateTime::class);

        // Configure default field resolver to be able to use getters
        GraphQL::setDefaultFieldResolver(new DefaultFieldResolver());

        //$rankTrackingType = $this->container->get(RankTrackingType::class);
        //$rankTrackingType = $types->get(RankTrackingType::class);

        try {
            $queryType = new ObjectType([
                'name' => 'query', // @todo Try change to Query
                'fields' => [

                    'rankTracking' => [
                        'type' => Type::listOf($this->types->get(RankTrackingType::class)),
                        'description' => 'Returns user by id (in range of 1-5)',
                        'args' => [
                            'productIds' => Type::nonNull(Type::listOf(Type::nonNull(Type::id()))),
                            //'productIds' => Type::nonNull(Type::id()),
                            //'lastDays' => Type::nonNull(Type::int()),
                            //'startedAt' => $this->container->get(\Stagem\ZfcGraphQL\Type\DateTimeType::class),
                            //'endedAt' => $this->container->get(\Stagem\ZfcGraphQL\Type\DateTimeType::class),

                            'startedAt' => $this->types->get(\DateTime::class),
                            'endedAt' => $this->types->get(\DateTime::class),
                        ],
                        'resolve' => function ($root, $args) {
                            $historyChartService = $this->container->get(HistoryChartService::class);

                            $product = $this->entityManager->find(Product::class, $args['productIds'][0]);
                            $marketplace = $this->entityManager->find(Marketplace::class, 1);

                            $result = $historyChartService->prepareChartData($product, $marketplace, ['startedAt' => $args['startedAt'], 'endedAt' => $args['endedAt']], 1);

                            return $result;
                        },
                    ],

                    /*'order' => [
                        'type' => Type::listOf($this->types->get(MarketOrderType::class)),
                        'description' => 'Returns orders',
                        'args' => [
                            //'orderIds' => Type::nonNull(Type::id()),
                            'orderIds' => Type::nonNull(Type::listOf(Type::nonNull(Type::id()))),
                            'startedAt' => $this->types->get(\DateTime::class),
                            'endedAt' => $this->types->get(\DateTime::class),
                        ],
                        'resolve' => function ($root, $args) {
                            $historyChartService = $this->container->get(HistoryChartService::class);

                            $orders = $this->entityManager->find(MarketOrder::class, $args['orderIds'][0]);
                            $marketplace = $this->entityManager->find(Marketplace::class, 1);

                            $result = $historyChartService->prepareChartData($orders, $marketplace, ['startedAt' => $args['startedAt'], 'endedAt' => $args['endedAt']], 1);

                            return $result;
                        },
                    ],*/


                    'product' => [
                        'type' => $this->types->getOutput(Product::class), // Use automated ObjectType for output
                        'description' => 'Returns product by id (in range of 1-6)',
                        'args' => [
                            'id' => Type::nonNull(Type::id())
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder = $this->types->createFilteredQueryBuilder(Product::class, $args['filter'] ?? [], $args['sorting'] ?? []);

                            $result = $queryBuilder->getQuery()->getArrayResult();

                            return $result;
                        },
                    ],



                    'review' => [
                        'type' => $this->types->getOutput(Review::class), // Use automated ObjectType for output
                        'description' => 'Returns review by id (in range of 1-6)',
                        'args' => [
                            'id' => Type::nonNull(Type::id())
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder = $this->types->createFilteredQueryBuilder(Review::class, $args['filter'] ?? [], $args['sorting'] ?? []);

                            $result = $queryBuilder->getQuery()->getArrayResult();

                            return $result;
                        },
                    ],

                    'customer' => [
                        'type' => $this->types->getOutput(Customer::class), // Use automated ObjectType for output
                        'description' => 'Returns customer by id (in range of 1-6)',
                        'args' => [
                            'id' => Type::nonNull(Type::id())
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder = $this->types->createFilteredQueryBuilder(Customer::class, $args['filter'] ?? [], $args['sorting'] ?? []);

                            $result = $queryBuilder->getQuery()->getArrayResult();

                            return $result;
                        },
                    ],


                    'customer' => [
                        'type' => $this->types->getOutput(Shipment::class), // Use automated ObjectType for output
                        'description' => 'Returns shipment by id (in range of 1-6)',
                        'args' => [
                            'id' => Type::nonNull(Type::id())
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder = $this->types->createFilteredQueryBuilder(Shipment::class, $args['filter'] ?? [], $args['sorting'] ?? []);

                            $result = $queryBuilder->getQuery()->getArrayResult();

                            return $result;
                        },
                    ],

                    'order' => [
                        'type' => $this->types->getOutput(MarketOrder::class), // Use automated ObjectType for output
                        'description' => 'Returns order by id (in range of 1-6)',
                        'args' => [
                            'id' => Type::nonNull(Type::id())
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder = $this->types->createFilteredQueryBuilder(MarketOrder::class, $args['filter'] ?? [], $args['sorting'] ?? []);
                            $result = $queryBuilder->getQuery()->getArrayResult();
                            return $result;

                            #$item = $this->entityManager->find(MarketOrder::class, $args['id']);
                            #return $item->asArray();
                        },
                    ],

                    'marketplace' => [
                        'type' => $this->types->getOutput(Marketplace::class), // Use automated ObjectType for output
                        'description' => 'Returns marketplace by id (in range of 1-6)',
                        'args' => [
                            'id' => Type::nonNull(Type::id())
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder = $this->types->createFilteredQueryBuilder(Marketplace::class, $args['filter'] ?? [], $args['sorting'] ?? []);

                            $result = $queryBuilder->getQuery()->getArrayResult();

                            return $result;
                        },
                    ],
                    'marketplaces' => [
                        'type' => Type::listOf($this->types->getOutput(Marketplace::class)), // Use automated ObjectType for output
                        'args' => [
                            [
                                'name' => 'filter',
                                'type' => $this->types->getFilter(Marketplace::class), // Use automated filtering options
                            ],
                            [
                                'name' => 'sorting',
                                'type' => $this->types->getSorting(Marketplace::class), // Use automated sorting options
                            ],
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder = $this->types->createFilteredQueryBuilder(Marketplace::class, $args['filter'] ?? [], $args['sorting'] ?? []);

                            $result = $queryBuilder->getQuery()->getArrayResult();

                            return $result;
                        },
                    ],

                    'role' => [
                        'type' => $this->types->getOutput(Role::class), // Use automated ObjectType for output
                        'description' => 'Returns user by id',
                        'args' => [
                            'id' => Type::nonNull(Type::id())
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder = $this->types->createFilteredQueryBuilder(Role::class, $args['filter'] ?? [], $args['sorting'] ?? []);

                            $result = $queryBuilder->getQuery()->getArrayResult();

                            return $result;
                        },
                    ],
                    'user' => [
                        'type' => $this->types->getOutput(User::class), // Use automated ObjectType for output
                        'description' => 'Returns user by id',
                        'args' => [
                            'id' => Type::nonNull(Type::id())
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder = $this->types->createFilteredQueryBuilder(User::class, $args['filter'] ?? [], $args['sorting'] ?? []);

                            $result = $queryBuilder->getQuery()->getArrayResult();

                            return $result;
                        },
                    ],

                    'orders' => [
                        'type' => Type::listOf($this->types->getOutput(MarketOrder::class)), // Use automated ObjectType for output
                        'args' => [
                            [
                                'name' => 'filter',
                                'type' => $this->types->getFilter(MarketOrder::class), // Use automated filtering options
                            ],
                            [
                                'name' => 'sorting',
                                'type' => $this->types->getSorting(MarketOrder::class), // Use automated sorting options
                            ],
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder = $this->types->createFilteredQueryBuilder(MarketOrder::class, $args['filter'] ?? [], $args['sorting'] ?? []);

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
                        'type' => Type::nonNull($this->types->getOutput(Marketplace::class)),
                        'args' => [
                            'input' => Type::nonNull($this->types->getInput(Marketplace::class)), // Use automated InputObjectType for input
                        ],
                        'resolve' => function ($root, $args): void {
                            // create new post and flush...
                        },
                    ],
                    'login' => [
                        //'type' => Type::nonNull($this->types->getOutput(User::class)),
                        'type' => new \GraphQL\Type\Definition\ObjectType([
                            'name' => 'Token',
                            'fields' => [
                                'token' => Type::nonNull(Type::string())
                            ]
                        ]),
                        'args' => [
                            'email' => Type::nonNull(Type::string()), // Use standard API when needed
                            'password' => Type::nonNull(Type::string()), // Use standard API when needed
                            //'input' => $this->types->getPartialInput(Post::class),  // Use automated InputObjectType for partial input for updates
                        ],
                        'resolve' => function ($root, $args) {
                            if ($user = $this->user()->current()) {
                                return ['token' => session_id()];
                            }
                            throw new InvalidArgumentException(
                                'GraphQLMiddleware cannot find user with credential passed to LoginMutation'
                            );
                        },
                    ],
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
            #$server = new StandardServer([
            #    'schema' => $schema
            #]);

            $context = new \stdClass();
            $context->request = $request;
            $context->user = $this->user()->current();
            $context->pool = $this->pool()->current();
            $context->entityManager = $this->entityManager;

            $config = ServerConfig::create()
                ->setSchema($schema)
                ->setContext($context)
                //->setErrorFormatter($myFormatter)
                //->setDebug($debug)
            ;

            $server = new StandardServer($config);

            $server->handleRequest();
        } catch (\Exception $e) {
            StandardServer::send500Error($e);
        }

        return new EmptyResponse();
    }
}