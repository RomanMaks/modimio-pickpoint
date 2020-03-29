<?php

use app\components\factory\FactoryHelper;
use app\components\factory\Factory;
use app\models\OrderBox;
use app\models\Order;
use Faker\Generator;

/**
 * @var Factory $factory
 **/

$factory->define(OrderBox::class, function (Generator $faker) {
    return [
        'order_id' => function () {
            return FactoryHelper::factory(Order::class)->create()->id;
        },
        'length' => $faker->randomFloat(10, 1, 10),
        'width' => $faker->randomFloat(10, 1, 10),
        'height' => $faker->randomFloat(10, 1, 10),
        'weight' => $faker->randomNumber(),
    ];
});