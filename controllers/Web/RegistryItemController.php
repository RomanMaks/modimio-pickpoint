<?php

namespace app\controllers\Web;

use app\controllers\Web\Actions\RegistryItem\Delete;

/**
 * Контроллер для работы с записями реестра
 *
 * Class RegistryItemController
 * @package app\controllers\Web
 */
class RegistryItemController extends WebController
{
    public function getViewPath()
    {
        return '@app/views/registry-item';
    }

    public function actions()
    {
        return [
            'delete' => Delete::class,
        ];
    }
}