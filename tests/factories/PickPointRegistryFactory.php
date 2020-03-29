<?php

use app\components\factory\FactoryHelper;
use app\components\factory\Factory;
use app\models\PickPointRegistry;
use app\models\PickPointRegistryItem;
use Faker\Generator;

/**
 * @var Factory $factory
 **/
$factory->define(PickPointRegistry::class, function (Generator $faker) {
    return [
        'registry_number' => '',
        'status' => PickPointRegistry::STATUSES['OPEN'],
        'label_print_link' => '',
    ];
});

/** После создания реестра, создаем запись в реестре */
$factory->afterCreating(PickPointRegistry::class, function (PickPointRegistry $registry) {
    FactoryHelper::factory(PickPointRegistryItem::class)->create([
        'registry_id' => $registry->id,
    ]);
});

/** Зарегистрированное состояние реестра с зарегистрированным отправлением */
$factory->state(
    PickPointRegistry::class,
    'createRegisteredRegistryWithRegisteredItem',
    function (Generator $faker) {
        return [
            'registry_number' => $faker->numberBetween(),
            'status' => PickPointRegistry::STATUSES['READY_FOR_SHIPMENT'],
            'label_print_link' => sprintf(
                'app/data/labels/registry_%d_%s.pdf',
                $faker->numberBetween(),
                date('YmdHis')
            ),
        ];
    }
);
$factory->afterCreatingState(
    PickPointRegistry::class,
    'createRegisteredRegistryWithRegisteredItem',
    function (PickPointRegistry $registry) {
        FactoryHelper::factory(PickPointRegistryItem::class)->state('createRegisteredItem')->create([
            'registry_id' => $registry->id,
        ]);
    }
);