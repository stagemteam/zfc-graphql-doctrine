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

namespace Stagem\ZfcGraphQL;

use Zend\Mvc\MvcEvent;
use Zend\Http\Request as HttpRequest;
use Zend\Session\Container as Session;
use Zend\ModuleManager\ModuleManager;
use Stagem\ZfcGraphQL\Service\Plugin\GraphPluginProviderInterface;

class Module
{
    public function getConfig()
    {
        $config = include __DIR__ . '/../config/module.config.php';
        $config['service_manager'] = $config['dependencies'];
        unset($config['dependencies']);

        return $config;
    }

    public function init(ModuleManager $moduleManager)
    {
        $container = $moduleManager->getEvent()->getParam('ServiceManager');
        $serviceListener = $container->get('ServiceListener');
        $serviceListener->addServiceManager(
        // The name of the plugin manager as it is configured in the service manager,
        // all config is injected into this instance of the plugin manager.
            'GraphPluginManager',
            // The key which is read from the merged module.config.php files, the
            // contents of this key are used as services for the plugin manager.
            'graph_plugins',
            // The interface which can be specified on a Module class for injecting
            // services into the plugin manager, using this interface in a Module
            // class is optional and depending on how your autoloader is configured
            // it may not work correctly.
            GraphPluginProviderInterface::class,
            // The function specified by the above interface, the return value of this
            // function is merged with the config from 'sample_plugins_config_key'.
            'getGraphPluginConfig'
        );
    }

    public function onBootstrap(MvcEvent $e)
    {
        #$app = $e->getApplication();
        #$eventManager = $app->getEventManager();
        #$container = $app->getServiceManager();

        // Set session ID before SessionManager initialization.
        // To do the same in GraphQLMiddleware is to late.
        $request = $e->getRequest();
        if ($request instanceof HttpRequest
            && ($header = $request->getHeaders()->get('authorization'))
            && ($sessionId = trim(str_replace('Bearer', '', $header->getFieldValue())))
        ) {
            $sessionManager = Session::getDefaultManager();
            // Start session with new session ID
            $sessionManager->setId($sessionId);
        } elseif ($request instanceof HttpRequest
            && ($sessionId = $request->getQuery(session_name()))
        ) {
            $sessionManager = Session::getDefaultManager();
            // Start session with new session ID
            $sessionManager->setId($sessionId);
        }
    }
}