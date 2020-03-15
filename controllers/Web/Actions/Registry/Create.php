<?php

namespace app\controllers\Web\Actions\Registry;

use app\services\RegistryService;
use yii\base\Action;

/**
 * Действие для создания нового реестра
 *
 * Class Create
 * @package app\controllers\Web\Actions\Registry
 */
class Create extends Action
{
    /**
     * @return string
     */
    public function run()
    {
        $service = new RegistryService();

        $errors = [];

        try {
            $service->createOrUpdate();
        } catch (\Throwable $exception) {
            $errors[] = $exception->getMessage();
        }

        return $this->controller->render('index', ['errors' => $errors]);
    }
}