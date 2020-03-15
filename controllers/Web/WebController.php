<?php

namespace app\controllers\Web;

use yii\web\Controller;

/**
 * Class WebController
 * @package app\controllers\Web
 */
class WebController extends Controller
{
    public $layout = 'main';

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        return $this->render('index');
    }
}