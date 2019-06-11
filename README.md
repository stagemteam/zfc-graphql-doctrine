# ZF GraphQL Doctrine
Zend Framework wrapper for [GraphQL Doctrine](https://github.com/Ecodev/graphql-doctrine) 


## Usage

The main idea is separate all queries and mutations in individual classes.

### Create new Query

Create new `ProductsQuery` under `src/App/Product/GraphQL/Query` which will return list of products.

```php
<?php

namespace App\Product\GraphQL\Query;

use GraphQL\Type\Definition\Type;
use GraphQL\Doctrine\Types;
use App\Product\Model\Product;

class ProductsQuery
{
    public function __invoke(Types $types)
    {
        return [
            'products' => [
                'type' => Type::listOf($types->getOutput(Product::class)), // Use automated ObjectType for output
                'args' => [
                    [
                        'name' => 'filter',
                        'type' => $types->getFilter(Product::class), // Use automated filtering options
                    ],
                    [
                        'name' => 'sorting',
                        'type' => $types->getSorting(Product::class), // Use automated sorting options
                    ],
                ],
                'resolve' => function ($root, $args) use ($types) {
                    $queryBuilder = $types->createFilteredQueryBuilder(Product::class, $args['filter'] ?? [], $args['sorting'] ?? []);
                    $result = $queryBuilder->getQuery()->getResult();

                    return $result;
                },
            ],
        ];
    }
}
```

Register all your queries in `module.config.php`
```php
<?php
// module/App/Product/config/module.config.php
return [
    'graphql' => [
        'queries' => [
            'paths' => [__DIR__ . '/../src/GraphQL/Query'],
        ],
    ],
    // ...
];
```

### Create new Mutation

Create new `CreateProductMutation` under `src/App/Product/GraphQL/Mutation` which will return create new product.

```php
<?php

namespace App\Product\GraphQL\Mutation;

use GraphQL\Type\Definition\Type;
use GraphQL\Doctrine\Types;
use App\Product\Model\Product;

class CreateProductMutation
{
    public function __invoke(Types $types)
    {
        return [
            'createProduct' => [
                'type' => Type::nonNull($types->getOutput(Product::class)),
                'args' => [
                    'input' => Type::nonNull($types->getInput(Product::class)),
                    // Use automated InputObjectType for input
                ],
                'resolve' => function ($root, $args): void {
                    // create new post and flush...
                },
            ],
        ];
    }
}
```

Register all your mutations in `module.config.php`
```php
<?php
// module/App/Product/config/module.config.php
return [
    'graphql' => [
        'mutations' => [
            'paths' => [__DIR__ . '/../src/GraphQL/Mutation'],
        ],
    ],
    // ...
];
```

