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
use function Functional\push;
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
use Stagem\GraphQL\Type\DateType;
use Stagem\GraphQL\Type\TimeType;
use Stagem\Order\Model\MarketOrder;
use Stagem\Product\GraphQL\Type\RankTrackingType;
use Stagem\Product\Model\Product;
use Stagem\Product\Service\HistoryChartService;
use Stagem\Product\Service\HistoryService;
use Stagem\Review\Model\Review;
use Stagem\Shipment\Model\Shipment;
use Stagem\ZfcConfigurator\Model\ConfiguratorAlgorithm;
use Stagem\ZfcConfigurator\Model\ConfiguratorItem;
use Stagem\ZfcConfigurator\Model\ConfiguratorJob;
use Popov\ZfcEntity\Model\Module;
use Stagem\ZfcConfigurator\Service\ConfiguratorAlgorithmService;
use Stagem\ZfcConfigurator\Service\ConfiguratorJobService;
use Stagem\ZfcProgress\Model\Progress;
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
    public function __construct(Types $types, EntityManager $entityManager, ContainerInterface $container, ServiceManager $serviceManager)
    {
        $this->types = $types;
        $this->container = $container;
        $this->entityManager = $entityManager;
        $this->serviceManager = $serviceManager;

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
                        ],
                        'resolve' => function ($root, $args) {
                            $historyChartService = $this->container->get(HistoryChartService::class);

                            $product = $this->entityManager->find(Product::class, $args['productIds'][0]);
                            $marketplace = $this->entityManager->find(Marketplace::class, 1);

                            $result = $historyChartService->prepareChartData($product, $marketplace, ['startedAt' => $args['startedAt'], 'endedAt' => $args['endedAt']], 1);

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
                            $qb->setParameter('updatedAtTo', (clone $args['updatedAt'])->setTime(23, 59, 59)->format('Y-m-d H:i:s'));
                            $items = $qb->getResult();

                            return $items;
                        },
                    ],

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

                    'products' => [
                        'type' => Type::listOf($this->types->getOutput(Product::class)), // Use automated ObjectType for output
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
                            $queryBuilder = $this->types->createFilteredQueryBuilder(Product::class, $args['filter'] ?? [], $args['sorting'] ?? []);

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

                    'shipment' => [
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
                        'description' => 'Returns order by id',
                        'args' => [
                            'id' => Type::nonNull(Type::id())
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
                            'startedAt' => $this->types->get(\DateTime::class),
                            'endedAt' => $this->types->get(\DateTime::class),
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder = $this->types->createFilteredQueryBuilder(MarketOrder::class, $args['filter'] ?? [], $args['sorting'] ?? []);

                            $result = $queryBuilder->getQuery()->getArrayResult();

                            return $result;
                        },
                    ],

                    'entity' => [
                        'type' => $this->types->getOutput(Entity::class), // Use automated ObjectType for output
                        'description' => 'Returns product by id (in range of 1-6)',
                        'args' => [
                            'id' => Type::nonNull(Type::id())
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder = $this->types->createFilteredQueryBuilder(Entity::class, $args['filter'] ?? [], $args['sorting'] ?? []);

                            $result = $queryBuilder->getQuery()->getArrayResult();

                            return $result;
                        },
                    ],

                    'entities' => [
                        'type' => Type::listOf($this->types->getOutput(Entity::class)), // Use automated ObjectType for output
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
                            $queryBuilder = $this->types->createFilteredQueryBuilder(Entity::class, $args['filter'] ?? [], $args['sorting'] ?? []);

                            $result = $queryBuilder->getQuery()->getResult();


                            return $result;
                        },
                    ],

                    'module' => [
                        'type' => $this->types->getOutput(Module::class), // Use automated ObjectType for output
                        'args' => [
                            'id' => Type::nonNull(Type::id())
                        ],
                        'resolve' => function ($root, $args) {
                            $result = $this->entityManager->find(Module::class, $args['id']);

                            return $result;
                        },
                    ],
                    'modules' => [
                        'type' => Type::listOf($this->types->getOutput(Module::class)), // Use automated ObjectType for output
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
                            $queryBuilder = $this->types->createFilteredQueryBuilder(Module::class, $args['filter'] ?? [], $args['sorting'] ?? []);

                            $result = $queryBuilder->getQuery()->getArrayResult();

                            return $result;
                        },
                    ],

                    'configuratorJobs' => [
                        'type' => Type::listOf($this->types->getOutput(ConfiguratorJob::class)), // Use automated ObjectType for output
                        'args' => [
                            [
                                'name' => 'filter',
                                'type' => $this->types->getFilter(ConfiguratorJob::class), // Use automated filtering options
                            ],
                            [
                                'name' => 'sorting',
                                'type' => $this->types->getSorting(ConfiguratorJob::class), // Use automated sorting options
                            ],
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder = $this->types->createFilteredQueryBuilder(ConfiguratorJob::class, $args['filter'] ?? [], $args['sorting'] ?? []);

                            $result = $queryBuilder->getQuery()->getResult();

                            return $result;
                        },
                    ],

                    'configuratorAlgorithms' => [
                        'type' => Type::listOf($this->types->getOutput(ConfiguratorAlgorithm::class)), // Use automated ObjectType for output
                        'args' => [
                            [
                                'name' => 'filter',
                                'type' => $this->types->getFilter(ConfiguratorAlgorithm::class), // Use automated filtering options
                            ],
                            [
                                'name' => 'sorting',
                                'type' => $this->types->getSorting(ConfiguratorAlgorithm::class), // Use automated sorting options
                            ],
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder = $this->types->createFilteredQueryBuilder(ConfiguratorAlgorithm::class, $args['filter'] ?? [], $args['sorting'] ?? []);

                            $result = $queryBuilder->getQuery()->getResult();

                            return $result;
                        },
                    ],

                    'configuratorItems' => [
                        'type' => Type::listOf($this->types->getOutput(ConfiguratorItem::class)), // Use automated ObjectType for output
                        'args' => [
                            [
                                'name' => 'filter',
                                'type' => $this->types->getFilter(ConfiguratorItem::class), // Use automated filtering options
                            ],
                            [
                                'name' => 'sorting',
                                'type' => $this->types->getSorting(ConfiguratorItem::class), // Use automated sorting options
                            ],
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder = $this->types->createFilteredQueryBuilder(ConfiguratorItem::class, $args['filter'] ?? [], $args['sorting'] ?? []);

                            $result = $queryBuilder->getQuery()->getResult();

                            return $result;
                        },
                    ],

                    'notifications' => [
                        'type' => Type::listOf($this->types->getOutput(Progress::class)),
                        'args' => [
                            [
                                'name' => 'filter',
                                'type' => $this->types->getFilter(Progress::class),
                            ],
                            [
                                'name' => 'sorting',
                                'type' => $this->types->getSorting(Progress::class),
                            ]
                        ],
                        'resolve' => function ($root, $args) {
                            $queryBuilder = $this->types->createFilteredQueryBuilder(Progress::class, $args['filter'] ?? [], $args['sorting'] ?? []);

                            $result = $queryBuilder->getQuery()->getResult();

                            return $result;
                        }
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

                    'runJob' => [
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

                            return call_user_func_array([$entity, $method[1]], [$job, null]);
                        },
                    ],

                    'addConfiguratorItem' => [
                        'type' => Type::listOf(Type::nonNull($this->types->getOutput(ConfiguratorItem::class))),
                        'args' => [
                            'itemId' => Type::listOf(Type::nonNull(Type::int())),
                            'entity' => Type::nonNull(Type::id()),
                            'configuratorJob' => Type::nonNull(Type::id())
                        ],
                        'resolve' => function ($root, $args) {
                            $entity = $this->entityManager->getRepository(Entity::class)->findOneBy(['id' => $args['entity']]);
                            $configuratorJob = $this->entityManager->getRepository(ConfiguratorJob::class)->findOneBy(['id' => $args['configuratorJob']]);

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
                            'configuratorJob' => Type::nonNull(Type::id())
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
                                                'configuratorJob' => $args['configuratorJob']
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
                            'isActive' => Type::nonNull(Type::int()),
                            'isBad' => Type::nonNull(Type::int()),
                            'when' => Type::nonNull(Type::string()),
                            'day' => Type::int(),
                            'time' => Type::nonNull(Type::string()),
                            'options' => Type::nonNull(Type::string()),
                            'entity' => Type::nonNull(Type::id()),
                            'pool' => Type::nonNull(Type::id()),
                            'configuratorAlgorithm' => Type::nonNull(Type::id())
                        ],
                        'resolve' => function($root, $args) {
                            $entity = $this->entityManager->getRepository(Entity::class)->findOneBy(['id' => $args['entity']]);
                            $pool = $this->entityManager->getRepository(Marketplace::class)->findOneBy(['id' => $args['pool']]);
                            $configuratorAlgorithm = $this->entityManager->getRepository(ConfiguratorAlgorithm::class)->findOneBy(['id' => $args['configuratorAlgorithm']]);

                            $configuratorJob = new ConfiguratorJob();
                            $configuratorJob->setName($args['name']);
                            $configuratorJob->setIsActive($args['isActive']);
                            $configuratorJob->setIsBad($args['isBad']);
                            $configuratorJob->setWhenTime($args['when']);
                            if ($args['when'] != 'everyday') {
                                $configuratorJob->setDayOfWhen($args['day']);
                            }
                            $configuratorJob->setTimeToRun(\DateTime::createFromFormat("H:i", $args['time']));
                            $configuratorJob->setOptions(json_decode($args['options'], true));
                            $configuratorJob->setEntity($entity);
                            $configuratorJob->setPool($pool);
                            $configuratorJob->setAlgorithm($configuratorAlgorithm);
                            $this->entityManager->persist($configuratorJob);
                            $this->entityManager->flush();

                            return $configuratorJob;
                        }
                    ],

                    'updateConfiguratorJob' => [
                        'type' => Type::nonNull($this->types->getOutput(ConfiguratorJob::class)),
                        'args' => [
                            'id' => Type::id(),
                            'name' => Type::string(),
                            'isActive' => Type::int(),
                            'isBad' => Type::int(),
                            'whenTime' => Type::string(),
                            'dayOfWhen' => Type::int(),
                            'timeToRun' => Type::string(),
                            'options' => Type::string(),
                            'pool' => Type::id(),
                        ],
                        'resolve' => function($root, $args) {
                            $pool = $this->entityManager->getRepository(Marketplace::class)->findOneBy(['id' => $args['pool']]);

                            $configuratorJob = $this->entityManager->getRepository(ConfiguratorJob::class)->findOneBy(['id' => $args['id']]);
                            if ($configuratorJob) {
                                foreach ($args as $key => $value) {
                                    if (isset($value) && $key != 'id') {
                                        if ($key == 'pool') {
                                            $configuratorJob->setPool($pool);
                                            continue;
                                        }

                                        if ($key == 'timeToRun') {
                                            $configuratorJob->setTimeToRun(\DateTime::createFromFormat("H:i", $args['timeToRun']));
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
                        }
                    ],

                    'recountNotification' => [
                        'type' => Type::string(),
                        'args' => [
                            'itemId' => Type::nonNull(Type::id()),
                            'options' => Type::nonNull(Type::string()),
                            'configuratorJob' => Type::nonNull(Type::id())
                        ],
                        'resolve' => function ($root, $args) {
                            $configuratorJob = $this->entityManager->getRepository(ConfiguratorJob::class)->findOneBy(['id' => $args['configuratorJob']]);
                            $algorithm = $configuratorJob->getAlgorithm();

                            $method = explode('::', $algorithm->getCallback());
                            $entity = $this->serviceManager->get($method[0]);

                            $configuratorJob->setOptions(json_decode($args['options'], true));

                            return call_user_func_array([$entity, $method[1]], [$configuratorJob, $args['itemId']]);
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
            StandardServer::send500Error($e);
        }

        return new EmptyResponse(200);
    }
}