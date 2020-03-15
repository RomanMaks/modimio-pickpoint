<?php

namespace app\controllers\web\actions\registry;

use app\services\RegistryService;
use yii\base\Action;
use yii\web\ServerErrorHttpException;

/**
 * Действие для создания нового реестра
 *
 * Class Create
 * @package app\controllers\web\actions\registry
 */
class Create extends Action
{
    /**
     * @return string
     * @throws ServerErrorHttpException
     */
    public function run()
    {
        $service = new RegistryService();

        $errors = [];

        try {
            $service->create();
        } catch (\Throwable $exception) {
            $errors[] = $exception->getMessage();
        }

        return $this->controller->render('create', ['errors' => $errors]);
    }
}