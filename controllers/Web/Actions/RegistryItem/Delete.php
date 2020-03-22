<?php

namespace app\controllers\Web\Actions\RegistryItem;

use app\models\PickPointRegistryItem;
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
     *
     * @throws \Exception
     */
    public function run()
    {
        $ids = \Yii::$app->request->post('ids');

        $items = PickPointRegistryItem::findAll(['id' => $ids]);

        $service = new RegistryService();

        $errors = [];

        try {
            $service->deleteItems($items);
        } catch (\Throwable $exception) {
            $errors[] = $exception->getMessage();
        }

        return $this->controller->render('index', ['errors' => $errors]);
    }
}