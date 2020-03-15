<?php

namespace app\controllers\web\actions\order;

use yii\base\Action;
use yii\web\ServerErrorHttpException;

/**
 * Действие для добавления заказа
 *
 * Class Create
 * @package app\controllers\web\actions\order
 */
class Create extends Action
{
    /**
     * @return string
     * @throws ServerErrorHttpException
     */
    public function run()
    {
        try {
            $service = new OrderService;
            $service->create();
        } catch (\Throwable $exception) {
            throw new ServerErrorHttpException($exception->getMessage());
        }

        return $this->controller->render('create');
    }
}