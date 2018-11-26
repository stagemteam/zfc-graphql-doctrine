<?php
/**
 * Project Plugin Manager
 *
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

namespace Stagem\ZfcGraphQL\Service\Plugin;

use Zend\Stdlib\Exception;
use Zend\ServiceManager\AbstractPluginManager;

class GraphPluginManager extends AbstractPluginManager
{
    /**
     * Default set of extension classes
     * Note: Use config notation for more flexibility
     *
     * @var array
     */
    protected $invokableClasses = [
        //'web-app' => 'Stagem\ZfcGraphQL\Service\Plugin\WebApp',
    ];

    public function validate($plugin)
    {
        //if ($plugin instanceof GraphPluginInterface) { // \GraphQL\Type\Definition\Type
        if ($plugin instanceof \GraphQL\Type\Definition\Type) { // \GraphQL\Type\Definition\Type
            // we're okay
            return;
        }

        throw new Exception\RuntimeException(sprintf(
            'Plugin of type %s is invalid; must implement %s\GraphPluginInterface',
            (is_object($plugin) ? get_class($plugin) : gettype($plugin)),
            __NAMESPACE__
        ));
    }
}
