<?php

namespace unit\models;

use app\models\Order;
use app\models\PickPointRegistry;
use app\services\PickPointAPIService;
use app\services\RegistryItemLogService;
use app\services\RegistryService;
use Codeception\Test\Unit;

/**
 * Unit тестирование реестра
 *
 * Class RegistryTest
 * @package unit\models
 */
class RegistryTest extends Unit
{
    /**
     * Создание mock для успешных ответов от RegistryService
     *
     * @return RegistryService
     *
     * @throws \Exception
     */
    protected function createMockRegistryServiceForSuccess(): object
    {
        // TODO: Необходимо переделать, сначало формировать заказы и
        // TODO: коробки в заказах, а не брать данные из БД, использовать
        // TODO: фикстуры для формирования записей
        $orders = Order::find()->all();

        $invoiceNumbers = array_map(
            function (Order $order) {
                return [
                    'InvoiceNumber' => (string)random_int(10000000, 99999999),
                    'SenderCode' => $order->alt_number
                ];
            },
            $orders
        );

        $pickPoint = $this->make(
            PickPointAPIService::class,
            [
                // Регистрация отправлений
                'createShipment' => ['created' => $invoiceNumbers, 'rejected' => []],

                // Формирование реестра
                'makeRegistryNumber' => '1',

                // Удаление отправления
                'cancelInvoice' => true,
            ]
        );

        return $this->make(
            RegistryService::class,
            [
                'pickPoint' => $pickPoint,
                'log' => new RegistryItemLogService,

                // Формирование этикеток
                'labeling' => '/data/labels/registry.pdf',
            ]
        );
    }

    /**
     * Тест на пересобирание реестра (удаление записей из зарегистрированного
     * реестра, создание и регистрация нового реестра по этим освободившимся
     * заказам)
     *
     * @throws \Exception
     */
    public function testRecreateRegistry()
    {
        // TODO: Хорошо бы использовать фикстуры
        $registryService = $this->createMockRegistryServiceForSuccess();

        // Создаем реестр
        $registry = $registryService->create();

        // Проверяем что реестр был создан
        $this->assertNotEmpty($registry);

        // Регистрируем отправления, получаем этикетки на них, регистрируем реестр
        $registryService->registration($registry);

        // Проверяем что реестр успешно зарегистрирован
        $this->assertTrue(PickPointRegistry::STATUSES['READY_FOR_SHIPMENT'] === $registry->status);

        $registryService->deleteItems($registry->items);

        $registry->refresh();

        $this->assertEmpty($registry->items);

        // Создаем новый реестр
        $registry = $registryService->create();

        // Проверяем что реестр был создан
        $this->assertNotEmpty($registry);

        // Регистрируем отправления, получаем этикетки на них, регистрируем реестр
        $registryService->registration($registry);

        // Проверяем что реестр успешно зарегистрирован
        $this->assertTrue(PickPointRegistry::STATUSES['READY_FOR_SHIPMENT'] === $registry->status);
    }


    /**
     * Тест на повторную регистрацию реестра (когда первая прошла с ошибкой)
     */
//    public function testRepeatedRegistrationRegistry()
//    {
//
//    }
}