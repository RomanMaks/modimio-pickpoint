<?php

use app\models\PickupPoint;
use Faker\Generator;
use app\components\factory\Factory;

/**
 * @var Factory $factory
 **/

$factory->define(PickupPoint::class, function (Generator $faker) {
    return [
        'code' => $faker->numerify('####-###'),
        'address' => $faker->address,
    ];
});