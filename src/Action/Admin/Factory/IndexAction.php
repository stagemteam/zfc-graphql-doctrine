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

namespace Stagem\ZfcGraphQL\Action\Admin\Factory;

use Psr\Container\ContainerInterface;

use Stagem\Product\GraphQL\Type\RankTrackingType;
use Stagem\ZfcGraphQL\Action\Admin\IndexAction;
use Stagem\ZfcGraphQL\Service\Plugin\GraphPluginManager;
use Stagem\ZfcGraphQL\Type\DateTimeType;


class IndexActionFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $graphPluginManager = $container->get(GraphPluginManager::class);
        $dateTimeType = $graphPluginManager->get(DateTimeType::class);

        $action = new IndexAction();
    }
}