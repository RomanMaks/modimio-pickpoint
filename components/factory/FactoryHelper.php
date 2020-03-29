<?php

namespace app\components\factory;

use yii\base\InvalidConfigException;

/**
 * Class FactoryHelper
 * @package app\components\factory
 */
class FactoryHelper
{
    /**
     * @return FactoryBuilder
     * @throws InvalidConfigException
     */
    public static function factory()
    {
        /** @var Factory $factory */
        $factory = \Yii::createObject(Factory::class);
        $arguments = func_get_args();
        if (isset($arguments[1]) && is_string($arguments[1])) {
            return $factory->of($arguments[0], $arguments[1])->times($arguments[2] ?? null);
        } elseif (isset($arguments[1])) {
            return $factory->of($arguments[0])->times($arguments[1]);
        } else {
            return $factory->of($arguments[0]);
        }
    }
}