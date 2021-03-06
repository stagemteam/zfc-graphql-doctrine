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
use Exception;
use function Functional\push;
use HaydenPierce\ClassFinder\ClassFinder;
use Popov\ZfcEntity\Model\Entity;
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
use Stagem\GraphQL\Type\DateTimeType;
use Stagem\GraphQL\Type\DateType;
use Stagem\GraphQL\Type\JsonType;
use Stagem\GraphQL\Type\TimeType;
use Stagem\Keyword\Model\ProductIgnore;
use Stagem\Keyword\Model\Keyword;
use Stagem\Keyword\Model\ProductMatching;
use Stagem\Notification\Model\Notification;
use Stagem\Order\Model\MarketOrder;
use Stagem\Order\Model\OrderSummary;
use Stagem\Order\Parser\OrderSummaryParser;
use Stagem\Order\Service\OrderSummaryService;
use Stagem\Parser\Service\ParserService;
use Stagem\Product\Block\Admin\Rank\BsrMonitorBlock;
use Stagem\Product\GraphQL\Type\BSRMonitorType;
use Stagem\Product\GraphQL\Type\RankTrackingType;
use Stagem\Product\Model\Product;
use Stagem\Product\Model\UserBsrSettings;
use Stagem\Product\Service\HistoryChartService;
use Stagem\Product\Service\HistoryService;
use Stagem\Report\Model\ReportType;
use Stagem\Review\Model\Review;
use Stagem\Review\Service\ReviewService;
use Stagem\ReviewPlan\Model\ReviewPlan;
use Stagem\ReviewPlan\Service\ReviewPlanService;
use Stagem\Shipment\Model\Shipment;
use Stagem\Configurator\Model\ConfiguratorAlgorithm;
use Stagem\Configurator\Model\ConfiguratorItem;
use Stagem\Configurator\Model\ConfiguratorJob;
use Popov\ZfcEntity\Model\Module;
use Stagem\Configurator\Service\ConfiguratorAlgorithmService;
use Stagem\Configurator\Service\ConfiguratorJobService;
use Stagem\ZfcProgress\Model\Progress;
use Stagem\ZfcStatus\Model\Status;
use Stagem\ZfcStatus\Service\StatusChanger;
use Stagem\ZfcStatus\Service\StatusService;
use Zend\Diactoros\Response\EmptyResponse;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Diactoros\Response\TextResponse;
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

    /**
     * @var ServiceManager
     */
    protected $serviceManager;

    //public function __construct(ContainerInterface $container, EntityManager $entityManager)
    //public function __construct(\Stagem\ZfcGraphQL\Service\Plugin\GraphPluginManager $container, EntityManager $entityManager)
    public function __construct(
        Types $types,
        EntityManager $entityManager,
        ContainerInterface $container,
        ServiceManager $serviceManager
    ) {
        $this->types = $types;
        $this->container = $container;
        $this->entityManager = $entityManager;
        $this->serviceManager = $serviceManager;
        //$this->config = $config;

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
                        'description' => 'Returns Rank Tracking in certain period',
                        'args' => [
                            'productIds' => Type::nonNull(Type::listOf(Type::nonNull(Type::id()))),
                            'startedAt' => $this->types->get(\DateTime::class),
                            'endedAt' => $this->types->get(\DateTime::class),
                            'marketplace' => Type::id(),
                        ],
                        'resolve' => function ($root, $args) {
                            $historyChartService = $this->container->get(HistoryChartService::class);
                            $product = $this->entityManager->find(Product::class, $args['productIds'][0]);
                            $marketplace = isset($args['marketplace']) ?
                                $this->entityManager->getRepository(Marketplace::class)
                                    ->findOneBy(['id' => $args['marketplace']]) :
                                $this->pool()->current();
                            $result = $historyChartService->prepareChartData($marketplace, $product,
                                ['startedAt' => $args['startedAt'], 'endedAt' => $args['endedAt']], 1);

                            return $result;
                        },
                    ],
                    'topRated' => [
                        'type' => Type::listOf($this->types->getOutput(\Stagem\Product\Model\History::class)),
                        'description' => 'Returns Top Rated products in certain period',
                        'args' => [
                            'profileRank' => Type::nonNull(Type::int()),
                            'updatedAt' => $this->types->get(DateType::class),
                        ],
                        'resolve' => function ($root, $args) {
                            $historyService = $this->container->get(HistoryService::class);
                            $qb = $historyService->getLatestSummaryHistories();
                            $qb->setParameter('profileRank', $args['profileRank']);
                            $qb->setParameter('updatedAt', $args['updatedAt']->format('Y-m-d H:i:s'));
                            $qb->setParameter('updatedAtTo',
                                (clone $args['updatedAt'])->setTime(23, 59, 59)->format('Y-m-d H:i:s'));
                            $items = $qb->getResult();

                            return $items;
                        },
                    ],
                    'product' => [
                        'type' => $this->types->getOutput(Product::class), // Use automated ObjectType for output
                        'description' => 'Returns product by id (in range of 1-6)',
                        'args' => [
                            'id' => Type::nonNull(Type::id()),
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder =
                                $this->types->createFilteredQueryBuilder(Product::class, $args['filter'] ?? [],
                                    $args['sorting'] ?? []);
                            $result = $queryBuilder->getQuery()->getArrayResult();

                            return $result;
                        },
                    ],
                    'products' => [
                        'type' => Type::listOf($this->types->getOutput(Product::class)),
                        // Use automated ObjectType for output
                        'args' => [
                            [
                                'name' => 'filter',
                                'type' => $this->types->getFilter(Product::class), // Use automated filtering options
                            ],
                            [
                                'name' => 'sorting',
                                'type' => $this->types->getSorting(Product::class), // Use automated sorting options
                            ],
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder =
                                $this->types->createFilteredQueryBuilder(Product::class, $args['filter'] ?? [],
                                    $args['sorting'] ?? []);
                            $result = $queryBuilder->getQuery()->getResult();

                            return $result;
                        },
                    ],
                    'review' => [
                        'type' => $this->types->getOutput(Review::class), // Use automated ObjectType for output
                        'description' => 'Returns review by id',
                        'args' => [
                            'id' => Type::nonNull(Type::id())
                            //'id' =>  $this->types->getId(Review::class)
                        ],
                        'resolve' => function ($root, $args) {
                            $item = $this->entityManager->find(Review::class, $args['id']);

                            return $item;
                        },
                    ],
                    'reviews' => [
                        'type' => Type::listOf($this->types->getOutput(Review::class)),
                        // Use automated ObjectType for output
                        'args' => [
                            [
                                'name' => 'filter',
                                'type' => $this->types->getFilter(Review::class), // Use automated filtering options
                            ],
                            [
                                'name' => 'sorting',
                                'type' => $this->types->getSorting(Review::class), // Use automated sorting options
                            ],
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder =
                                $this->types->createFilteredQueryBuilder(Review::class, $args['filter'] ?? [],
                                    $args['sorting'] ?? []);
                            $result = $queryBuilder->getQuery()->getArrayResult();

                            return $result;
                        },
                    ],
                    'reviewStars' => [
                        'type' => Type::listOf(new \GraphQL\Type\Definition\ObjectType([
                            'name' => 'reviewStar',
                            'fields' => [
                                'rate' => Type::nonNull(Type::string()),
                                'rateCount' => Type::nonNull(Type::int()),
                                'createdAt' => $this->types->get(\DateTime::class),
                                'isRemoved' => Type::nonNull(Type::int()),
                            ],
                        ])),
                        'args' => [
                            'marketplace' => Type::id(),
                            'startedAt' => $this->types->get(\DateTime::class),
                            'endedAt' => $this->types->get(\DateTime::class),
                        ],
                        'resolve' => function ($root, $args) {
                            $marketplace =
                                isset($args['marketplace']) ? $this->entityManager->getRepository(Marketplace::class)
                                    ->findOneBy(['id' => $args['marketplace']]) : null;
                            $dates['startedAt'] = $args['startedAt'];
                            $dates['endedAt'] = $args['endedAt'];
                            $data = $this->serviceManager->get(ReviewService::class)
                                ->getReviewsStarsWithDates($marketplace, $dates);

                            return $data;
                        },
                    ],
                    'customer' => [
                        'type' => $this->types->getOutput(Customer::class), // Use automated ObjectType for output
                        'description' => 'Returns customer by id (in range of 1-6)',
                        'args' => [
                            'id' => Type::nonNull(Type::id()),
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder =
                                $this->types->createFilteredQueryBuilder(Customer::class, $args['filter'] ?? [],
                                    $args['sorting'] ?? []);
                            $result = $queryBuilder->getQuery()->getArrayResult();

                            return $result;
                        },
                    ],
                    'shipment' => [
                        'type' => $this->types->getOutput(Shipment::class), // Use automated ObjectType for output
                        'description' => 'Returns shipment by id (in range of 1-6)',
                        'args' => [
                            'id' => Type::nonNull(Type::id()),
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder =
                                $this->types->createFilteredQueryBuilder(Shipment::class, $args['filter'] ?? [],
                                    $args['sorting'] ?? []);
                            $result = $queryBuilder->getQuery()->getArrayResult();

                            return $result;
                        },
                    ],
                    'order' => [
                        'type' => $this->types->getOutput(MarketOrder::class), // Use automated ObjectType for output
                        'description' => 'Returns order by id',
                        'args' => [
                            'id' => Type::nonNull(Type::id()),
                        ],
                        'resolve' => function ($root, $args) {
                            //                            $queryBuilder = $this->types->createFilteredQueryBuilder(MarketOrder::class, $args['filter'] ?? [], $args['sorting'] ?? []);
                            //                            $result = $queryBuilder->getQuery()->getArrayResult();
                            //                            return $result;
                            $item = $this->entityManager->find(MarketOrder::class, $args['id']);

                            return $item;
                            #$item = $this->entityManager->find(MarketOrder::class, $args['id']);
                            #return $item->asArray();
                        },
                    ],
                    'marketplace' => [
                        'type' => $this->types->getOutput(Marketplace::class), // Use automated ObjectType for output
                        'description' => 'Returns marketplace by id (in range of 1-6)',
                        'args' => [
                            'id' => Type::nonNull(Type::id()),
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder =
                                $this->types->createFilteredQueryBuilder(Marketplace::class, $args['filter'] ?? [],
                                    $args['sorting'] ?? []);
                            $result = $queryBuilder->getQuery()->getArrayResult();

                            return $result;
                        },
                    ],
                    'marketplaces' => [
                        'type' => Type::listOf($this->types->getOutput(Marketplace::class)),
                        // Use automated ObjectType for output
                        'args' => [
                            [
                                'name' => 'filter',
                                'type' => $this->types->getFilter(Marketplace::class),
                                // Use automated filtering options
                            ],
                            [
                                'name' => 'sorting',
                                'type' => $this->types->getSorting(Marketplace::class), // Use automated sorting options
                            ],
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder =
                                $this->types->createFilteredQueryBuilder(Marketplace::class, $args['filter'] ?? [],
                                    $args['sorting'] ?? []);
                            $result = $queryBuilder->getQuery()->getArrayResult();

                            return $result;
                        },
                    ],
                    'role' => [
                        'type' => $this->types->getOutput(Role::class), // Use automated ObjectType for output
                        'description' => 'Returns user by id',
                        'args' => [
                            'id' => Type::nonNull(Type::id()),
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder = $this->types->createFilteredQueryBuilder(Role::class, $args['filter'] ?? [],
                                $args['sorting'] ?? []);
                            $result = $queryBuilder->getQuery()->getArrayResult();

                            return $result;
                        },
                    ],
                    'user' => [
                        'type' => $this->types->getOutput(User::class), // Use automated ObjectType for output
                        'description' => 'Returns user by id',
                        'args' => [
                            'id' => Type::nonNull(Type::id()),
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder = $this->types->createFilteredQueryBuilder(User::class, $args['filter'] ?? [],
                                $args['sorting'] ?? []);
                            $result = $queryBuilder->getQuery()->getArrayResult();

                            return $result;
                        },
                    ],
                    'orders' => [
                        'type' => Type::listOf($this->types->getOutput(MarketOrder::class)),
                        // Use automated ObjectType for output
                        'args' => [
                            [
                                'name' => 'filter',
                                'type' => $this->types->getFilter(MarketOrder::class),
                                // Use automated filtering options
                            ],
                            [
                                'name' => 'sorting',
                                'type' => $this->types->getSorting(MarketOrder::class), // Use automated sorting options
                            ],
                            'startedAt' => $this->types->get(\DateTime::class),
                            'endedAt' => $this->types->get(\DateTime::class),
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder =
                                $this->types->createFilteredQueryBuilder(MarketOrder::class, $args['filter'] ?? [],
                                    $args['sorting'] ?? []);
                            $result = $queryBuilder->getQuery()->getArrayResult();

                            return $result;
                        },
                    ],
                    'entity' => [
                        'type' => $this->types->getOutput(Entity::class), // Use automated ObjectType for output
                        'description' => 'Returns product by id (in range of 1-6)',
                        'args' => [
                            'id' => Type::nonNull(Type::id()),
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder =
                                $this->types->createFilteredQueryBuilder(Entity::class, $args['filter'] ?? [],
                                    $args['sorting'] ?? []);
                            $result = $queryBuilder->getQuery()->getArrayResult();

                            return $result;
                        },
                    ],
                    'entities' => [
                        'type' => Type::listOf($this->types->getOutput(Entity::class)),
                        // Use automated ObjectType for output
                        'args' => [
                            [
                                'name' => 'filter',
                                'type' => $this->types->getFilter(Entity::class), // Use automated filtering options
                            ],
                            [
                                'name' => 'sorting',
                                'type' => $this->types->getSorting(Entity::class), // Use automated sorting options
                            ],
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder =
                                $this->types->createFilteredQueryBuilder(Entity::class, $args['filter'] ?? [],
                                    $args['sorting'] ?? []);
                            $result = $queryBuilder->getQuery()->getResult();

                            return $result;
                        },
                    ],
                    'module' => [
                        'type' => $this->types->getOutput(Module::class), // Use automated ObjectType for output
                        'args' => [
                            'id' => Type::nonNull(Type::id()),
                        ],
                        'resolve' => function ($root, $args) {
                            $result = $this->entityManager->find(Module::class, $args['id']);

                            return $result;
                        },
                    ],
                    'modules' => [
                        'type' => Type::listOf($this->types->getOutput(Module::class)),
                        // Use automated ObjectType for output
                        'args' => [
                            [
                                'name' => 'filter',
                                'type' => $this->types->getFilter(Module::class), // Use automated filtering options
                            ],
                            [
                                'name' => 'sorting',
                                'type' => $this->types->getSorting(Module::class), // Use automated sorting options
                            ],
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder =
                                $this->types->createFilteredQueryBuilder(Module::class, $args['filter'] ?? [],
                                    $args['sorting'] ?? []);
                            $result = $queryBuilder->getQuery()->getArrayResult();

                            return $result;
                        },
                    ],
                    'configuratorJobs' => [
                        'type' => Type::listOf($this->types->getOutput(ConfiguratorJob::class)),
                        // Use automated ObjectType for output
                        'args' => [
                            [
                                'name' => 'filter',
                                'type' => $this->types->getFilter(ConfiguratorJob::class),
                                // Use automated filtering options
                            ],
                            [
                                'name' => 'sorting',
                                'type' => $this->types->getSorting(ConfiguratorJob::class),
                                // Use automated sorting options
                            ],
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder =
                                $this->types->createFilteredQueryBuilder(ConfiguratorJob::class, $args['filter'] ?? [],
                                    $args['sorting'] ?? []);
                            $result = $queryBuilder->getQuery()->getResult();

                            return $result;
                        },
                    ],
                    'configuratorAlgorithms' => [
                        'type' => Type::listOf($this->types->getOutput(ConfiguratorAlgorithm::class)),
                        // Use automated ObjectType for output
                        'args' => [
                            [
                                'name' => 'filter',
                                'type' => $this->types->getFilter(ConfiguratorAlgorithm::class),
                                // Use automated filtering options
                            ],
                            [
                                'name' => 'sorting',
                                'type' => $this->types->getSorting(ConfiguratorAlgorithm::class),
                                // Use automated sorting options
                            ],
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder = $this->types->createFilteredQueryBuilder(ConfiguratorAlgorithm::class,
                                $args['filter'] ?? [], $args['sorting'] ?? []);
                            $result = $queryBuilder->getQuery()->getResult();

                            return $result;
                        },
                    ],
                    'configuratorItems' => [
                        'type' => Type::listOf($this->types->getOutput(ConfiguratorItem::class)),
                        // Use automated ObjectType for output
                        'args' => [
                            [
                                'name' => 'filter',
                                'type' => $this->types->getFilter(ConfiguratorItem::class),
                                // Use automated filtering options
                            ],
                            [
                                'name' => 'sorting',
                                'type' => $this->types->getSorting(ConfiguratorItem::class),
                                // Use automated sorting options
                            ],
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder =
                                $this->types->createFilteredQueryBuilder(ConfiguratorItem::class, $args['filter'] ?? [],
                                    $args['sorting'] ?? []);
                            $result = $queryBuilder->getQuery()->getResult();

                            return $result;
                        },
                    ],
                    'notifications' => [
                        'type' => Type::listOf($this->types->getOutput(Notification::class)),
                        'args' => [
                            [
                                'name' => 'filter',
                                'type' => $this->types->getFilter(Notification::class),
                            ],
                            [
                                'name' => 'sorting',
                                'type' => $this->types->getSorting(Notification::class),
                            ],
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder =
                                $this->types->createFilteredQueryBuilder(Notification::class, $args['filter'] ?? [],
                                    $args['sorting'] ?? []);
                            $result = $queryBuilder->getQuery()->getResult();

                            return $result;
                        },
                    ],
                    'statuses' => [
                        'type' => Type::listOf($this->types->getOutput(Status::class)),
                        'args' => [
                            [
                                'name' => 'filter',
                                'type' => $this->types->getFilter(Status::class),
                            ],
                            [
                                'name' => 'sorting',
                                'type' => $this->types->getSorting(Status::class),
                            ],
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder =
                                $this->types->createFilteredQueryBuilder(Status::class, $args['filter'] ?? [],
                                    $args['sorting'] ?? []);
                            $result = $queryBuilder->getQuery()->getResult();

                            return $result;
                        },
                    ],
                    'orderSummaries' => [
                        'type' => Type::listOf($this->types->getOutput(OrderSummary::class)),
                        'args' => [
                            [
                                'name' => 'filter',
                                'type' => $this->types->getFilter(OrderSummary::class),
                            ],
                            [
                                'name' => 'sorting',
                                'type' => $this->types->getSorting(OrderSummary::class),
                            ],
                        ],
                        'resolve' => function ($root, $args) {
                            //$marketplace = $this->pool()->current();
                            //$args['filter']['marketplace'] = $marketplace;
                            $qb = $this->types->createFilteredQueryBuilder(OrderSummary::class, $args['filter'] ?? [],
                                $args['sorting'] ?? []);
                            $result = $qb->getQuery()->getResult();

                            return $result;
                        },
                    ],
                    'reportType' => [
                        'type' => Type::listOf($this->types->getOutput(ReportType::class)),
                        'args' => [
                            [
                                'name' => 'filter',
                                'type' => $this->types->getFilter(ReportType::class),
                            ],
                            [
                                'name' => 'sorting',
                                'type' => $this->types->getSorting(ReportType::class),
                            ],
                        ],
                        'resolve' => function ($root, $args) {
                            $qb = $this->types->createFilteredQueryBuilder(ReportType::class, $args['filter'] ?? [],
                                $args['sorting'] ?? []);
                            $result = $qb->getQuery()->getResult();

                            return $result;
                        },
                    ],
                ],
                'resolveField' => function ($val, $args, $context, ResolveInfo $info) {
                    return $this->{$info->fieldName}($val, $args, $context, $info);
                },
            ]);
            $mutationType = new ObjectType([
                'name' => 'mutation',
                'fields' => [
                    'createMarketplace' => [
                        'type' => Type::nonNull($this->types->getOutput(Marketplace::class)),
                        'args' => [
                            'input' => Type::nonNull($this->types->getInput(Marketplace::class)),
                            // Use automated InputObjectType for input
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
                                'token' => Type::nonNull(Type::string()),
                            ],
                        ]),
                        'args' => [
                            'email' => Type::nonNull(Type::string()),
                            // Use standard API when needed
                            'password' => Type::nonNull(Type::string()),
                            // Use standard API when needed
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
                    'logout' => [
                        'type' => new \GraphQL\Type\Definition\ObjectType([
                            'name' => 'Logout',
                            'fields' => [
                                'token' => Type::boolean(),
                            ],
                        ]),
                        /*'args' => [
                            'email' => Type::nonNull(Type::string()), // Use standard API when needed
                            'password' => Type::nonNull(Type::string()), // Use standard API when needed
                            //'input' => $this->types->getPartialInput(Post::class),  // Use automated InputObjectType for partial input for updates
                        ],*/
                        'resolve' => function ($root, $args) {
                            return ['token' => false];
                        },
                    ],
                    'runJob' => [
                        //'type' => Type::listOf(Type::string()),
                        'type' => Type::string(),
                        'args' => [
                            'jobId' => Type::nonNull(Type::string()), // Use standard API when needed
                        ],
                        'resolve' => function ($root, $args) {
                            $job = $this->serviceManager->get(ConfiguratorJobService::class)
                                ->getConfiguratorJobWithId($args['jobId'])
                                ->getQuery()
                                ->getSingleResult();
                            $algorithm = $this->serviceManager->get(ConfiguratorAlgorithmService::class)
                                ->getConfiguratorAlgorithmWithId($job->getAlgorithm()->getId())
                                ->getQuery()
                                ->getSingleResult();
                            $method = explode('::', $algorithm->getCallback());
                            $entity = $this->serviceManager->get($method[0]);
                            if ($method[1] == "updateEtalon") {
                                $result = call_user_func_array([$entity, $method[1]], [$job]);
                            } else {
                                $result = call_user_func_array([$entity, $method[1]], [$job, null]);
                            }
                            $this->entityManager->flush();

                            return $result;
                        },
                    ],
                    'addConfiguratorItem' => [
                        'type' => Type::listOf(Type::nonNull($this->types->getOutput(ConfiguratorItem::class))),
                        'args' => [
                            'itemId' => Type::listOf(Type::nonNull(Type::int())),
                            'entity' => Type::nonNull(Type::id()),
                            'configuratorJob' => Type::nonNull(Type::id()),
                        ],
                        'resolve' => function ($root, $args) {
                            $entity = $this->entityManager->getRepository(Entity::class)
                                ->findOneBy(['id' => $args['entity']]);
                            $configuratorJob = $this->entityManager->getRepository(ConfiguratorJob::class)
                                ->findOneBy(['id' => $args['configuratorJob']]);
                            $itemsIds = $args['itemId'];
                            $configuratorItems = [];
                            if (!empty($itemsIds)) {
                                foreach ($itemsIds as $itemsId) {
                                    $configuratorItem = new ConfiguratorItem();
                                    $configuratorItem->setItemId($itemsId);
                                    $configuratorItem->setEntity($entity);
                                    $configuratorItem->setConfiguratorJob($configuratorJob);
                                    $this->entityManager->persist($configuratorItem);
                                    array_push($configuratorItems, $configuratorItem);
                                }
                                $this->entityManager->flush();

                                return $configuratorItems;
                            }

                            return new \Exception('Nothing was added.');
                        },
                    ],
                    'deleteConfiguratorItem' => [
                        'type' => Type::listOf(Type::nonNull($this->types->getOutput(ConfiguratorItem::class))),
                        'args' => [
                            'itemId' => Type::listOf(Type::nonNull(Type::int())),
                            'entity' => Type::nonNull(Type::id()),
                            'configuratorJob' => Type::nonNull(Type::id()),
                        ],
                        'resolve' => function ($root, $args) {
                            $itemsIds = $args['itemId'];
                            $configuratorItems = [];
                            if (!empty($itemsIds)) {
                                foreach ($itemsIds as $itemsId) {
                                    $configuratorItem = $this->entityManager->getRepository(ConfiguratorItem::class)
                                        ->findOneBy(
                                            [
                                                'itemId' => $itemsId,
                                                'entity' => $args['entity'],
                                                'configuratorJob' => $args['configuratorJob'],
                                            ]);
                                    array_push($configuratorItems, $configuratorItem);
                                    $this->entityManager->remove($configuratorItem);
                                }
                                $this->entityManager->flush();

                                return $configuratorItems;
                            }

                            return new \Exception('Nothing was deleted.');
                        },
                    ],
                    'addConfiguratorJob' => [
                        'type' => Type::nonNull($this->types->getOutput(ConfiguratorJob::class)),
                        'args' => [
                            'name' => Type::nonNull(Type::string()),
                            'type' => Type::nonNull(Type::string()),
                            'isActive' => Type::nonNull(Type::int()),
                            'isBad' => Type::nonNull(Type::int()),
                            'when' => Type::nonNull(Type::string()),
                            'day' => Type::int(),
                            'time' => Type::string(),
                            'options' => Type::nonNull(Type::string()),
                            'entity' => Type::nonNull(Type::id()),
                            'pool' => Type::nonNull(Type::id()),
                            'configuratorAlgorithm' => Type::nonNull(Type::string()),
                        ],
                        'resolve' => function ($root, $args) {
                            $entity = $this->entityManager->getRepository(Entity::class)
                                ->findOneBy(['id' => $args['entity']]);
                            $pool = $this->entityManager->getRepository(Marketplace::class)
                                ->findOneBy(['id' => $args['pool']]);
                            $configuratorAlgorithm = $this->entityManager->getRepository(ConfiguratorAlgorithm::class)
                                ->findOneBy(['mnemo' => $args['configuratorAlgorithm']]);
                            $configuratorJob = new ConfiguratorJob();
                            $configuratorJob->setName($args['name']);
                            $configuratorJob->setType($args['type']);
                            $configuratorJob->setIsActive($args['isActive']);
                            $configuratorJob->setIsBad($args['isBad']);
                            $configuratorJob->setWhenTime($args['when']);
                            if ($args['when'] != 'everyday') {
                                $configuratorJob->setDayOfWhen($args['day']);
                            }
                            $configuratorJob->setTimeToRun($args['time'] ? \DateTime::createFromFormat("H:i",
                                $args['time']) : null);
                            if (strlen($args['options']) > 0) {
                                $configuratorJob->setOptions(json_decode($args['options'], true));
                            } else {
                                $configuratorJob->setOptions([]);
                            }
                            $configuratorJob->setEntity($entity);
                            $configuratorJob->setPool($pool);
                            $configuratorJob->setAlgorithm($configuratorAlgorithm);
                            $this->entityManager->persist($configuratorJob);
                            $this->entityManager->flush();

                            return $configuratorJob;
                        },
                    ],
                    'updateConfiguratorJob' => [
                        'type' => Type::nonNull($this->types->getOutput(ConfiguratorJob::class)),
                        'args' => [
                            'id' => Type::id(),
                            'type' => Type::string(),
                            'name' => Type::string(),
                            'isActive' => Type::int(),
                            'isBad' => Type::int(),
                            'whenTime' => Type::string(),
                            'dayOfWhen' => Type::int(),
                            'timeToRun' => Type::string(),
                            'options' => Type::string(),
                            'pool' => Type::id(),
                        ],
                        'resolve' => function ($root, $args) {
                            $pool = $this->entityManager->getRepository(Marketplace::class)
                                ->findOneBy(['id' => $args['pool']]);
                            $configuratorJob = $this->entityManager->getRepository(ConfiguratorJob::class)
                                ->findOneBy(['id' => $args['id']]);
                            if ($configuratorJob) {
                                foreach ($args as $key => $value) {
                                    if (isset($value) && $key != 'id') {
                                        if ($key == 'pool') {
                                            $configuratorJob->setPool($pool);
                                            continue;
                                        }
                                        if ($key == 'timeToRun') {
                                            $configuratorJob->setTimeToRun(\DateTime::createFromFormat("H:i",
                                                $args['timeToRun']));
                                            continue;
                                        }
                                        if ($key == 'options') {
                                            $value = json_decode($value, true);
                                        }
                                        $configuratorJob->{'set' . ucfirst($key)}($value);
                                    }
                                }
                                if ($args['whenTime'] == 'everyday') {
                                    $configuratorJob->setDayOfWhen(null);
                                }
                                $this->entityManager->merge($configuratorJob);
                                $this->entityManager->flush();

                                return $configuratorJob;
                            } else {
                                return new \Exception('Configurator job not found');
                            }
                        },
                    ],
                    'recountNotification' => [
                        'type' => Type::string(),
                        'args' => [
                            'itemId' => Type::nonNull(Type::id()),
                            'options' => Type::nonNull(Type::string()),
                            'configuratorJob' => Type::nonNull(Type::id()),
                        ],
                        'resolve' => function ($root, $args) {
                            $configuratorJob = $this->entityManager->getRepository(ConfiguratorJob::class)
                                ->findOneBy(['id' => $args['configuratorJob']]);
                            $algorithm = $configuratorJob->getAlgorithm();
                            $method = explode('::', $algorithm->getCallback());
                            $entity = $this->serviceManager->get($method[0]);
                            $configuratorJob->setOptions(json_decode($args['options'], true));

                            return call_user_func_array([$entity, $method[1]], [$configuratorJob, $args['itemId']]);
                        },
                    ],
                    //Еhe decision was made to return exactly Progress class
                    'changeStatus' => [
                        'type' => Type::nonNull($this->types->getOutput(Notification::class)),
                        'args' => [
                            'itemMnemo' => Type::nonNull(Type::string()),
                            'itemId' => Type::nonNull(Type::id()),
                            'statusId' => Type::nonNull(Type::id()),
                        ],
                        'resolve' => function ($root, $args) {
                            /** @var StatusChanger $serviceChanger */
                            $serviceChanger = $this->serviceManager->get(StatusChanger::class);
                            $serviceChanger->change($args['itemMnemo'], $args['itemId'], $args['statusId']);
                            $modifiedNotification = $this->entityManager->getRepository(Notification::class)
                                ->findOneBy(['id' => $args['itemId']]);

                            return $modifiedNotification;
                        },
                    ],
                    'updateOrdersIsTester' => [
                        'type' => Type::listOf(Type::nonNull($this->types->getOutput(MarketOrder::class))),
                        'args' => [
                            'orders' => Type::listOf(Type::nonNull(Type::string())),
                        ],
                        'resolve' => function ($root, $args) {
                            // fixed bug with loosing last order causing \t symbol in the end
                            foreach ($args['orders'] as $key => $order) {
                                $args['orders'][$key] = trim($order);
                            }
                            $orders = $this->entityManager->getRepository(MarketOrder::class)
                                ->findBy(['code' => $args['orders']]);
                            //$ordersSummary =
                            $marketplace = $this->pool()->current();
                            $dates = [];
                            $orderSummaryRows = [];
                            foreach ($orders as $order) {
                                $order->setIsTest(true);
                                //$this->entityManager->merge($order);
                                $orderPurchaseAt = (clone $order->getPurchaseAt())->setTime(0, 0);
                                if (!isset($orderSummaryRows[$orderPurchaseAt->format('Y-m-d')])) {
                                    //$dates[$orderPurchaseAt->format('Y-m-d')] = $orderPurchaseAt->setTime(0,0);
                                    $fromRepository = $this->entityManager->getRepository(OrderSummary::class)
                                        ->findOneBy([
                                            'marketplace' => $marketplace,
                                            'date' => $orderPurchaseAt,
                                        ]);
                                    if ($fromRepository) {
                                        $orderSummaryRows[$orderPurchaseAt->format('Y-m-d')] = $fromRepository;
                                    }
                                }
                            }
                            $this->entityManager->flush();
                            $orderSummaryService = $this->container->get(OrderSummaryService::class);
                            $summaryParser = new OrderSummaryParser($orderSummaryService);
                            $orderSummaryRows = $summaryParser->processCertainDates($orderSummaryRows, $marketplace);
                            $this->entityManager->flush(); //updated orderSummary table

                            return $orders;
                        },
                    ],
                    'updateReviewsIsTester' => [
                        'type' => Type::listOf(Type::nonNull($this->types->getOutput(Review::class))),
                        'args' => [
                            'reviewData' => Type::listOf(Type::nonNull(Type::string())),
                        ],
                        'resolve' => function ($root, $args) {
                            $reviews = [];
                            $data = $args['reviewData'];
                            /**
                             * @var Review $review
                             * @var MarketOrder $order
                             */
                            foreach ($data as $item) {
                                $parsedItem = json_decode($item, true);
                                $review = isset($parsedItem['reviewCode']) ?
                                    $this->entityManager->getRepository(Review::class)
                                        ->findOneBy(['code' => $parsedItem['reviewCode']]) : null;
                                $order = isset($parsedItem['orderCode']) ?
                                    $this->entityManager->getRepository(MarketOrder::class)
                                        ->findOneBy(['code' => $parsedItem['orderCode']]) : null;
                                if ($review && $order) {
                                    $review->setIsTest(true);
                                    $review->setMarketOrder($order);
                                    $review->setOrderCode($order->getCode());
                                    $order->setIsTest(true);
                                    $this->entityManager->merge($review);
                                    $this->entityManager->merge($order);
                                    $reviews[] = $review;
                                } elseif ($review) {
                                    $review->setIsTest(true);
                                    $this->entityManager->merge($review);
                                    $reviews[] = $review;
                                }
                            }
                            $this->entityManager->flush();

                            return $reviews;
                        },
                    ],
                    'orderReviews' => [
                        'type' => Type::listOf($this->types->getOutput(ReviewPlan::class)),
                        'args' => [
                            'orderReviewsData' => Type::nonNull(Type::string()),
                        ],
                        'resolve' => function ($root, $args) {
                            $reviewsPlans = [];
                            /** @var ReviewPlanService $reviewPlan */
                            $reviewPlan = $this->serviceManager->get(ReviewPlanService::class);
                            if (isset($args['orderReviewsData'])) {
                                $reviewsPlans = $reviewPlan->orderReviews($args['orderReviewsData']);
                                $this->entityManager->flush();
                            }

                            return $reviewsPlans;
                        },
                    ],
                    'addListKeywords' => [
                        'type' => Type::listOf(Type::nonNull($this->types->getOutput(Keyword::class))),
                        'args' => [
                            'keywordData' => Type::listOf(Type::nonNull(Type::string())),
                        ],
                        'resolve' => function ($root, $args) {
                            $keywords = [];
                            $data = $args['keywordData'];
                            /**
                             * @var Keyword $keywords
                             */
                            foreach ($data as $item) {
                                $parsedItem = json_decode($item, true);
                                /** @var Marketplace $marketplace */
                                $marketplace = isset($parsedItem['marketplace']) ?
                                    $this->entityManager->getRepository(Marketplace::class)
                                        ->findOneBy(['id' => $parsedItem['marketplace']]) : null;
                                /** @var Product $product */
                                $product = isset($parsedItem['asin']) ?
                                    $this->entityManager->getRepository(Product::class)
                                        ->findOneBy(['asin' => $parsedItem['asin']]) : null;
                                $keyword = isset($parsedItem['keyword']) ? $parsedItem['keyword'] : null;
                                $isMain = isset($parsedItem['isMain']) ? $parsedItem['isMain'] : 0;
                                /** @var Keyword $newKeyword */
                                if ($marketplace && $product && $keyword) {

                                    /** @var Keyword $keywordExists */
                                    $keywordExists = $this->entityManager->getRepository(Keyword::class)
                                        ->findOneBy([
                                            'product' => $product,
                                            'marketplace' => $marketplace,
                                            'keyword' => $keyword,
                                        ]);
                                    if (!$keywordExists) {
                                        if ($isMain == 1) {
                                            $isMainKeywords = $this->entityManager->getRepository(Keyword::class)
                                                ->findBy([
                                                    'product' => $product,
                                                    'marketplace' => $marketplace,
                                                    'isMain' => 1,
                                                ]);
                                            /** @var Keyword $isMainKeyword */
                                            foreach ($isMainKeywords as $isMainKeyword) {
                                                $isMainKeyword->setIsMain(0);
                                                $this->entityManager->merge($isMainKeyword);
                                            }
                                        }
                                        $newKeyword = new Keyword();
                                        $newKeyword->setProduct($product);
                                        $newKeyword->setMarketplace($marketplace);
                                        $newKeyword->setKeyword($keyword);
                                        $newKeyword->setIsMain($isMain);
                                        $this->entityManager->persist($newKeyword);
                                        $keywords[] = $newKeyword;
                                    } else {
                                        if ($isMain != $keywordExists->getisMain()) {
                                            if ($isMain == 1) {
                                                $isMainKeywords = $this->entityManager->getRepository(Keyword::class)
                                                    ->findBy([
                                                        'product' => $product,
                                                        'marketplace' => $marketplace,
                                                        'isMain' => 1,
                                                    ]);
                                                /** @var Keyword $isMainKeyword */
                                                foreach ($isMainKeywords as $isMainKeyword) {
                                                    $isMainKeyword->setIsMain(0);
                                                    $this->entityManager->merge($isMainKeyword);
                                                }
                                            }
                                            $keywordExists->setIsMain($isMain);
                                            $this->entityManager->merge($keywordExists);
                                            $keywords[] = $keywordExists;
                                        }
                                    }
                                }
                            }
                            $this->entityManager->flush();

                            return $keywords;
                        },
                    ],
                    'listMatchingProduct' => [
                        'type' => Type::listOf($this->types->getOutput(ProductMatching::class)),
                        'args' => [
                            'keywords' => Type::listOf(Type::nonNull(Type::string())),
                            'asinOur' => Type::nonNull(Type::string()),
                        ],
                        'resolve' => function ($root, $args) {
                            $listMatchingProducts = [];
                            $keywords = $this->entityManager->getRepository(Keyword::class)
                                ->findBy(['keyword' => $args['keywords']]);
                            $asinOur = $args['asinOur'];
                            if (!empty($keywords)) {
                                /** @var Keyword $keyword */
                                foreach ($keywords as $keyword) {
                                    $keyword->setIsNeedParse(1);
                                    $this->entityManager->merge($keyword);
                                }
                                $this->entityManager->flush();
                                //$lastListMatchingProductId = $this->entityManager->getRepository(ProductsMatching::class)
                                //    ->getLastInserted()->getQuery()->getSingleScalarResult();
                                try {
                                    /** @var ParserService $parserService */
                                    $parserService = $this->serviceManager->get(ParserService::class);
                                    $parserService->parse('stagem-keyword-product-matching-parse');
                                    /** @var Keyword $keyword */
                                    foreach ($keywords as $keyword) {
                                        $keyword->setIsNeedParse(0);
                                        //$keyword->setMarketplace($keyword->getMarketplace()->getId());
                                        $this->entityManager->merge($keyword);
                                    }
                                    $this->entityManager->flush();
                                    /*$listMatchingProducts = $this->entityManager->getRepository(ListMatchingProduct::class)
                                        ->getMatchingProductsGreaterId($lastListMatchingProductId)->getQuery()->getResult();*/
                                    //$listMatchingProducts = $this->entityManager->getRepository(\Stagem\Keyword\Model\ListMatchingProduct::class)->findBy(['id'=>10]);
                                    $listMatchingProducts =
                                        $this->entityManager->getRepository(ProductMatching::class)->findBy([
                                            'keyword' => $keywords,
                                            'asinOur' => $asinOur,
                                        ]);
                                } catch (Exception $exception) {
                                    foreach ($keywords as $keyword) {
                                        $keyword->setIsNeedParse(0);
                                        $this->entityManager->merge($keyword);
                                    }
                                    $this->entityManager->flush();
                                }
                            }

                            return $listMatchingProducts;
                        },
                    ],
                    'sendToList' => [
                        'type' => Type::listOf($this->types->getOutput(Product::class)),
                        'args' => [
                            'productData' => Type::listOf(Type::nonNull(Type::string())),
                        ],
                        'resolve' => function ($root, $args) {
                            $products = [];
                            $data = $args['productData'];
                            foreach ($data as $item) {
                                $parsedItem = json_decode($item, true);
                                /** @var ProductMatching $productMatching */
                                $productMatching = $this->entityManager->getRepository(ProductMatching::class)
                                    ->findOneBy(['id' => $parsedItem['id']]);
                                if (isset($productMatching)) {
                                    /** @var Marketplace $itemMarketplace */
                                    $itemMarketplace = $this->entityManager->getRepository(Marketplace::class)
                                        ->findOneBy(['code' => $productMatching->getMarketplaceCode()]);
                                    /** @var Product $product */
                                    $product = $this->entityManager->getRepository(Product::class)
                                        ->findOneBy(['asin' => $productMatching->getAsin()]);
                                    if ($product) {
                                        if (!$product->inMarketplace($itemMarketplace)) {
                                            $product->addMarketplace($itemMarketplace);
                                        }
                                        $product->setOriginalAsin($productMatching->getAsinOur());
                                        $product->setName($productMatching->getName());
                                        $product->setBrand($productMatching->getBrand());
                                        $product->setManufacturer($productMatching->getManufacturer());
                                        $product->setPublisher($productMatching->getPublisher());
                                        $product->setStudio($productMatching->getStudio());
                                        $product->setTitle($productMatching->getTitle());
                                        $product->setSmallImage($productMatching->getSmallImageUrl());
                                        $this->entityManager->merge($product);
                                        $products[] = $product;
                                    } else {
                                        $newProduct = new Product();
                                        $newProduct->setAsin($productMatching->getAsin());
                                        $newProduct->setName($productMatching->getName());
                                        $newProduct->setOriginalAsin($productMatching->getAsinOur());
                                        $newProduct->setIsOriginal(0);
                                        $newProduct->setPosition(10);
                                        $newProduct->setIsActive(1);
                                        $newProduct->setBrand($productMatching->getBrand());
                                        $newProduct->setManufacturer($productMatching->getManufacturer());
                                        $newProduct->setPublisher($productMatching->getPublisher());
                                        $newProduct->setStudio($productMatching->getStudio());
                                        $newProduct->setTitle($productMatching->getTitle());
                                        $newProduct->setSmallImage($productMatching->getSmallImageUrl());
                                        $newProduct->addMarketplace($itemMarketplace);
                                        $this->entityManager->persist($newProduct);
                                        $products[] = $newProduct;
                                    }
                                    $productMatching->setAction("2_skip_asin_competitor");
                                }
                            }
                            $this->entityManager->flush();

                            return $products;
                        },
                    ],
                    'sendToIgnore' => [
                        'type' => Type::listOf($this->types->getOutput(ProductIgnore::class)),
                        'args' => [
                            'asinIgnoreData' => Type::listOf(Type::nonNull(Type::string())),
                        ],
                        'resolve' => function ($root, $args) {
                            $ignoredAsins = [];
                            $data = $args['asinIgnoreData'];
                            foreach ($data as $item) {
                                $parsedItem = json_decode($item, true);
                                /** @var ProductMatching $productMatching */
                                $productMatching = $this->entityManager->getRepository(ProductMatching::class)
                                    ->findOneBy(['id' => $parsedItem['id']]);
                                if (isset($productMatching)) {
                                    /** @var Marketplace $itemMarketplace */
                                    $itemMarketplace = $this->entityManager->getRepository(Marketplace::class)
                                        ->findOneBy(['code' => $productMatching->getMarketplaceCode()]);
                                    /** @var ProductIgnore $isIgnored */
                                    $isIgnored = $this->entityManager->getRepository(ProductIgnore::class)
                                        ->getAsinIgnoreByMarketplaceAsin($itemMarketplace, $productMatching->getAsin())
                                        ->getQuery()->getOneOrNullResult();
                                    if ($isIgnored) {
                                        $isIgnored->setTitle($productMatching->getTitle());
                                        $isIgnored->setAsinOur($productMatching->getAsinOur());
                                        $isIgnored->setImageUrl($productMatching->getSmallImageUrl());
                                        $isIgnored->setAddedAt(new \DateTime());
                                        $this->entityManager->merge($isIgnored);
                                        $ignoredAsins[] = $isIgnored;
                                    } else {
                                        $newProductIgnore = new ProductIgnore();
                                        $newProductIgnore->setAsin($productMatching->getAsin());
                                        $newProductIgnore->setTitle($productMatching->getTitle());
                                        $newProductIgnore->setAsinOur($productMatching->getAsinOur());
                                        $newProductIgnore->setImageUrl($productMatching->getSmallImageUrl());
                                        $newProductIgnore->setAddedAt(new \DateTime());
                                        $newProductIgnore->setMarketplace($itemMarketplace);
                                        $this->entityManager->persist($newProductIgnore);
                                        $ignoredAsins[] = $newProductIgnore;
                                    }
                                    $productMatching->setAction("3_asin_in_ignore");
                                    $this->entityManager->merge($productMatching);
                                }
                            }
                            $this->entityManager->flush();

                            return $ignoredAsins;
                        },
                    ],
                    'keywordMatchingClear' => [
                        'type' => Type::listOf($this->types->getOutput(ProductMatching::class)),
                        'args' => [
                            'keywordMatchingData' => Type::listOf(Type::nonNull(Type::string())),
                        ],
                        'resolve' => function ($root, $args) {
                            $keywordMatchingProducts = $this->entityManager->getRepository(ProductMatching::class)
                                ->getAllMatchingProducts($this->pool()->current())
                                ->getQuery()->getResult();
                            foreach ($keywordMatchingProducts as $index => $keywordMatchingProduct) {
                                if (strlen(trim($keywordMatchingProduct->getAction())) != 0
                                    && $keywordMatchingProduct->getAction() != "0_select_what_to_do") {
                                    $this->entityManager->remove($keywordMatchingProduct);
                                    unset($keywordMatchingProducts[$index]);
                                }
                            }
                            $this->entityManager->flush();

                            return $keywordMatchingProducts;
                        },
                    ],
                    'runQueueAction' => [
                        'type' => Type::string(),
                        'args' => [
                            'jobId' => Type::nonNull(Type::ID()),
                            'action' => Type::nonNull(Type::string()),
                        ],
                        'resolve' => function ($root, $args) {
                            /** @var ConfiguratorJob $job */
                            $job = $this->entityManager->getRepository(ConfiguratorJob::class)
                                ->findOneBy(['id' => $args['jobId']]);
                            /** @var Module $module */
                            $module =
                                $this->entityManager->getRepository(Module::class)->findOneBy(['mnemo' => 'report']);
                            $namespace = $module->getName() . "\Service\QueueService";
                            $entity = $this->serviceManager->get($namespace);
                            $result = call_user_func_array([$entity, $args['action'] . "Queue"], [$job]);
                            $this->entityManager->flush();

                            return $result;
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
            #ob_start();
            $server->handleRequest();
            #$result = ob_get_contents();
            #ob_end_clean();
            #return new JsonResponse();
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            StandardServer::send500Error($e);
        }

        return new EmptyResponse(200);
    }
}
