<?php
/**
 * Plugin Factory
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

use Zend\Mvc\Service\AbstractPluginManagerFactory;

class GraphPluginFactory extends AbstractPluginManagerFactory
{
    const PLUGIN_MANAGER_CLASS = GraphPluginManager::class;
}