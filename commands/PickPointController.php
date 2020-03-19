<?php

namespace app\commands;

use app\services\RegistryService;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Эта команда проходит все шаги для регистрации реестра
 *
 * Class PickPointController
 * @package app\commands
 */
class PickPointController extends Controller
{
    protected const CATEGORY_ERROR = 'API PickPoint';

    /**
     * Запуск регистрации реестра
     *
     * @return int Exit code
     */
    public function actionIndex()
    {
        $this->info('Создание сервиса по работе с реестром');

        try {
            $registryService = new RegistryService();
        } catch (\Throwable $exception) {
            $this->error(ExitCode::getReason(ExitCode::SOFTWARE));
            return ExitCode::SOFTWARE;
        }

        $this->info('Сервис успешно создан');

        $this->info('Создание нового реестра если он еще не создан');

        try {
            $registry = $registryService->create();
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return ExitCode::SOFTWARE;
        }

        $this->info('Реестр успешно создан');

        $this->info('Регистрация реестра');

        try {
            $registryService->registration($registry);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return ExitCode::SOFTWARE;
        }

        $this->info('Реестр успешно зарегистрирован');

        $this->info(ExitCode::getReason(ExitCode::OK));

        return ExitCode::OK;
    }

    /**
     * Вывод ошибки в консоль
     *
     * @param string $message
     */
    protected function error(string $message)
    {
        $this->stdout('[ERROR]', Console::BG_RED);

        $this->stdout('[' . self::CATEGORY_ERROR . '] ' . $message . PHP_EOL);
    }

    /**
     * Вывод инормативного сообщения в консоль
     *
     * @param string $message
     */
    protected function info(string $message)
    {
        $this->stdout('[INFO]', Console::BG_GREEN);

        $this->stdout('[' . self::CATEGORY_ERROR . '] ' . $message . PHP_EOL);
    }
}