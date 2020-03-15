<?php

namespace app\controllers\web;

use app\controllers\web\actions\order\Create;

/**
 * Контроллер для работы с заказами
 *
 * Class OrderController
 * @package app\controllers\web
 */
class OrderController extends WebController
{
    public function getViewPath()
    {
        return '@app/views/order';
    }

    public function actions()
    {
        return [
            'create' => Create::class,
        ];
    }
}