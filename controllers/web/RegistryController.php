<?php

namespace app\controllers\web;

use app\controllers\web\actions\registry\Create;

/**
 * Контроллер для работы с реестрами
 *
 * Class RegistryController
 * @package app\controllers\web
 */
class RegistryController extends WebController
{
    public function getViewPath()
    {
        return '@app/views/registry';
    }

    public function actions()
    {
        return [
            'create' => Create::class,
        ];
    }
}