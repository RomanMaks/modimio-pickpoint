<?php

use app\components\factory\FactoryHelper;
use app\components\factory\Factory;
use app\models\Order;
use app\models\OrderBox;
use app\models\PickupPoint;
use Faker\Generator;

/**
 * @var Factory $factory
 **/

$factory->define(Order::class, function (Generator $faker) {
    return [
        'alt_number' => $faker->unique()->numerify(),
        'total_price' => $faker->numberBetween(1000, 9999),
        'delivery_price' => $faker->numberBetween(100, 999),
        'catalog_pay_id' => $faker->randomElement(Order::PAYMENT_METHODS),
        'pickup_point_id' => function () {
            return FactoryHelper::factory(PickupPoint::class)->create()->id;
        },
        'user_name' => $faker->firstName,
        'user_patronymic' => $faker->lastName,
        'user_surname' => $faker->lastName,
        'user_phone' => $faker->phoneNumber,
        'user_email' => $faker->email,
    ];
});

/** После создания заказа, создаем добавляем коробку в него */
$factory->afterCreating(Order::class, function (Order $order) {
    FactoryHelper::factory(OrderBox::class)->create([
        'order_id' => $order->id,
    ]);
});