<?php
define('YII_ENV', 'test');
defined('YII_DEBUG') or define('YII_DEBUG', true);

require_once __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';
require __DIR__ .'/../vendor/autoload.php';

Yii::$container->setSingleton(
    \app\components\factory\Factory::class,
    [],
    [
        \Faker\Factory::create('ru_RU'),
        __DIR__ . '/factories'
    ]
);