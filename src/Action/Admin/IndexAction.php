<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2018 Stagem Team
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

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// @todo wait until they will start to use Psr in codebase @see https://github.com/zendframework/zend-mvc/blob/master/src/MiddlewareListener.php#L11
//use Psr\Http\Server\MiddlewareInterface;
//use Psr\Http\Server\RequestHandlerInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Interop\Http\Server\RequestHandlerInterface;

use Zend\Diactoros\Response\EmptyResponse;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use GraphQL\Doctrine\DefaultFieldResolver;
use GraphQL\Doctrine\Types;
use Doctrine\ORM\EntityManager;
use Stagem\ClassFinder\ClassFinder;
use Stagem\ZfcAction\Page\AbstractAction;

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
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var array
     */
    protected $config = [];

    public function __construct(
        Types $types,
        EntityManager $entityManager,
        ContainerInterface $container,
        array $config
    ) {
        $this->types = $types;
        $this->container = $container;
        $this->entityManager = $entityManager;
        $this->config = $config;

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
        // Configure default field resolver to be able to use getters
        GraphQL::setDefaultFieldResolver(new DefaultFieldResolver());

        try {
            // Fetch GraphQL queries
            $pathToQueries = $this->config['graphql']['queries']['paths'] ?? [];
            $classes = [];
            foreach ($pathToQueries as $dir) {
                $classes = array_merge($classes, (new ClassFinder())->getClassesInDir($dir));
            }
            $queryFields = [];
            foreach ($classes as $queryClass) {
                $query = $this->container->get($queryClass);
                $queryFields += $query($this->types);
            }

            $queryType = new ObjectType([
                'name' => 'query', // @todo Try change to Query
                'fields' => $queryFields,
                'resolveField' => function ($val, $args, $context, ResolveInfo $info) {
                    return $this->{$info->fieldName}($val, $args, $context, $info);
                },
            ]);

            // Fetch GraphQL mutations
            $pathToMutations = $this->config['graphql']['mutations']['paths'] ?? [];
            $classes = [];
            foreach ($pathToMutations as $dir) {
                $classes = array_merge($classes, (new ClassFinder())->getClassesInDir($dir));
            }
            $mutationFields = [];
            foreach ($classes as $mutationClass) {
                $mutation = $this->container->get($mutationClass);
                $mutationFields += $mutation($this->types);
            }

            $mutationType = new ObjectType([
                'name' => 'mutation',
                'fields' => $mutationFields,
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
            #var_dump($e->getMessage());
            StandardServer::send500Error($e);
        }

        return new EmptyResponse(200);
    }
}
