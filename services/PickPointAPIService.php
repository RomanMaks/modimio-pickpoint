<?php

namespace app\services;

use app\exceptions\PickPoint\FailedRegisterShipmentException;
use app\exceptions\PickPoint\FailedToDeleteInvoiceException;
use app\exceptions\PickPoint\FailedToFormLabelsException;
use app\exceptions\PickPoint\FailedToFormRegistryException;
use app\exceptions\PickPoint\InternalServerErrorException;
use app\exceptions\PickPoint\InvalidResponseException;
use app\exceptions\PickPoint\LoginFailedException;
use app\exceptions\PickPoint\PickPointApiException;
use app\models\Session;
use yii\helpers\Json;
use yii\httpclient\Client;
use yii\httpclient\Response;

/**
 * Сервис по работе с API PickPoint
 *
 * Class PickPointService
 * @package app\services
 */
class PickPointAPIService
{
    /** @var Client $client */
    protected $client;

    /** @var string $sessionId */
    protected $sessionId;

    protected const CATALOG_LOG = 'API PickPoint';

    /**
     * PickPointAPIService constructor.
     *
     * @throws PickPointApiException
     */
    public function __construct()
    {
        $this->client = new Client([
            'baseUrl' => \Yii::$app->params['pickpoint']['url'],
        ]);

        $session = Session::findOne(['service' => self::class]);

        // ЕСЛИ токен пустой ИЛИ текущее время больше времени жизни токена ТО перевыпустить
        if (empty($session) ||
            (new \DateTime) > (new \DateTime($session->issued_at))->add(new \DateInterval('P1D'))) {
            $this->refresh();
        } else {
            $this->sessionId = $session->token;
        }
    }

    /**
     * Отправка запроса
     *
     * @param string $url
     * @param array $content
     * @param string $method
     *
     * @return Response
     *
     * @throws InternalServerErrorException
     * @throws InvalidResponseException
     */
    protected function sendRequest(string $url, array $content, string $method = 'POST'): Response
    {
        try {
            \Yii::info(
                sprintf('REQUEST::URL: %s; CONTENT: %s', $url, json_encode($content)),
                self::CATALOG_LOG
            );
            $response = $this->client->createRequest()
                ->setMethod($method)
                ->setUrl($url)
                ->addHeaders(['Content-Type' => 'application/json'])
                ->setContent(Json::encode($content))
                ->send();
        } catch (\Throwable $exception) {
            \Yii::error(
                sprintf('URL: %s; ERROR: %s', $url, $exception->getMessage()),
                self::CATALOG_LOG
            );
            throw new InternalServerErrorException($exception->getMessage());
        }

        if (!$response->isOk) {
            \Yii::error(
                sprintf('RESPONSE::URL: %s; CONTENT: %s', $url, $response->content),
                self::CATALOG_LOG
            );
            throw new InvalidResponseException($response->getContent());
        }

        return $response;
    }

    /**
     * Обновить токен
     *
     * @throws InternalServerErrorException
     * @throws InvalidResponseException
     * @throws LoginFailedException
     */
    protected function refresh()
    {
        $session = Session::findOne(['service' => self::class]);

        $this->sessionId = $this->getSession();

        if (empty($session)) {
            $session = new Session;
            $session->service = self::class;
        }

        $session->token = $this->sessionId;
        $session->issued_at = date('Y-m-d H:i:s');

        $session->save();
    }

    /**
     * Получить номер сессии для дальнейшей работы в остальных методах
     *
     * @return string
     *
     * @throws InternalServerErrorException
     * @throws InvalidResponseException
     * @throws LoginFailedException
     */
    protected function getSession(): string
    {
        $content = [
            'Login' => \Yii::$app->params['pickpoint']['login'] ?? '',
            'Password' => \Yii::$app->params['pickpoint']['password'] ?? '',
        ];

        $response = $this->sendRequest('/login', $content);

        if (!empty($response->data['ErrorCode'])) {
            \Yii::error(
                sprintf('RESPONSE::URL: %s; CONTENT: %s', '/login', $response->content),
                self::CATALOG_LOG
            );
            throw new LoginFailedException($response->data['ErrorMessage'], $response->data['ErrorCode']);
        }

        return $response->data['SessionId'];
    }

    /**
     * Регистрация отправлений
     *
     * @param array $sendings
     *
     * @return array
     *
     * @throws InternalServerErrorException
     * @throws InvalidResponseException
     * @throws FailedRegisterShipmentException
     */
    public function createShipment(array $sendings): array
    {
        $content = [
            'SessionId' => $this->sessionId,
            'Sendings' => $sendings
        ];

        $response = $this->sendRequest('/CreateShipment', $content);

        if (!empty($response->data['ErrorCode'])) {
            \Yii::error(
                sprintf('RESPONSE::URL: %s; CONTENT: %s', '/CreateShipment', $response->content),
                self::CATALOG_LOG
            );
            throw new FailedRegisterShipmentException($response->data['Error']);
        }

        return [
            'created' => $response->data['CreatedSendings'],
            'rejected' => $response->data['RejectedSendings']
        ];
    }

    /**
     * Формирование реестра
     *
     * @param array $sending
     *
     * @return string
     *
     * @throws InternalServerErrorException
     * @throws InvalidResponseException
     * @throws FailedToFormRegistryException
     */
    public function createRegistry(array $sending): string
    {
        $response = $this->sendRequest(
            '/makereestrnumber',
            array_merge(['SessionId' => $this->sessionId], $sending)
        );

        if (!empty($response->data['ErrorCode'])) {
            \Yii::error(
                sprintf('RESPONSE::URL: %s; CONTENT: %s','/makereestrnumber', $response->content),
                self::CATALOG_LOG
            );
            throw new FailedToFormRegistryException($response->data['ErrorMessage']);
        }

        return $response->data['Numbers'][0];
    }

    /**
     * Формирование этикеток в pdf
     *
     * @param array $invoices
     *
     * @return string
     *
     * @throws InternalServerErrorException
     * @throws InvalidResponseException
     * @throws FailedToFormLabelsException
     */
    public function makelabel(array $invoices): string
    {
        $content = [
            'SessionId' => $this->sessionId,
            'Invoices' => $invoices,
        ];

        $response = $this->sendRequest('/makelabel', $content);

        if ('Error' === mb_substr($response->content, 0, 5) || '%PDF' !== mb_substr($response->content, 0, 4)) {
            \Yii::error(
                sprintf('RESPONSE::URL: %s; CONTENT: %s', '/makelabel', $response->content),
                self::CATALOG_LOG
            );
            throw new FailedToFormLabelsException('Произошла ошибка при создании этикетки');
        }

        return $response->content;
    }

    /**
     * Удаление отправления
     *
     * @param array $invoice
     *
     * @return bool
     *
     * @throws FailedToDeleteInvoiceException
     * @throws InternalServerErrorException
     * @throws InvalidResponseException
     */
    public function cancelInvoice(array $invoice): bool
    {
        $content = array_merge(['SessionId' => $this->sessionId], $invoice);

        $response = $this->sendRequest('/cancelInvoice', $content);

        if (!empty($response->data['ErrorCode'])) {
            \Yii::error(
                sprintf('RESPONSE::URL: %s; CONTENT: %s','/cancelInvoice', $response->content),
                self::CATALOG_LOG
            );
            throw new FailedToDeleteInvoiceException($response->data['Error']);
        }

        return true;
    }
}