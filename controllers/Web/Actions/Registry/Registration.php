<?php

namespace app\controllers\Web\Actions\Registry;

use app\models\PickPointRegistry;
use app\services\RegistryService;
use yii\base\Action;
use yii\web\NotFoundHttpException;

/**
 * Действие зарегестрирует реестр в PickPoint
 *
 * Class Registration
 * @package app\controllers\Web\Actions\Registry
 */
class Registration extends Action
{
    /**
     * @param int $id
     *
     * @return string
     *
     * @throws NotFoundHttpException
     */
    public function run(int $id)
    {
        $registry = PickPointRegistry::findOne(['id' => $id]);
        if (empty($registry)) {
            throw new NotFoundHttpException('Страница не существует.');
        }

        $service = new RegistryService();

        $errors = [];

        try {
            $service->registration($registry);
        } catch (\Throwable $exception) {
            $errors[] = $exception->getMessage();
        }

        return $this->controller->render('index', ['errors' => $errors]);
    }
}