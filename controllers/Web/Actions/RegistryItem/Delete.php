<?php

namespace app\controllers\Web\Actions\RegistryItem;

use app\services\RegistryService;
use yii\base\Action;

/**
 * Действие для удаления записей из реестра
 *
 * Class Delete
 * @package app\controllers\Web\Actions\RegistryItem
 */
class Delete extends Action
{
    /**
     * @return string
     */
    public function run()
    {
        $ids = [1, 2, 3];

        $service = new RegistryService();

        $errors = [];

        try {
            $service->deleteItems($ids);
        } catch (\Throwable $exception) {
            $errors[] = $exception->getMessage();
        }

        return $this->controller->render('index', ['errors' => $errors]);
    }
}