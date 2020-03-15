<?php

namespace app\controllers\Web;

use app\controllers\Web\Actions\Registry\Create;
use app\controllers\Web\Actions\Registry\Registration;

/**
 * Контроллер для работы с реестрами
 *
 * Class RegistryController
 * @package app\controllers\Web
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
            'registration' => Registration::class
        ];
    }
}