<?php

use app\components\factory\FactoryHelper;
use app\components\factory\Factory;
use app\models\PickPointRegistryItem;
use app\models\PickPointRegistry;
use app\models\Order;
use Faker\Generator;

/**
 * @var Factory $factory
 **/
$factory->define(PickPointRegistryItem::class, function (Generator $faker) {
    return [
        'departure_track_code' => '',
        'status' => PickPointRegistryItem::STATUSES['CREATE'],
        'registry_id' => function () {
            return FactoryHelper::factory(PickPointRegistry::class)->create()->id;
        },
        'order_id' => function () {
            return FactoryHelper::factory(Order::class)->create()->id;
        },
    ];
});

/** Зарегистрированное состояние отправления в PickPoint */
$factory->state(PickPointRegistryItem::class, 'createRegisteredItem', function (Generator $faker) {
    return [
        'departure_track_code' => $faker->unique()->numberBetween(10000000000, 99999999999),
        'status' => PickPointRegistryItem::STATUSES['REGISTERED'],
    ];
});