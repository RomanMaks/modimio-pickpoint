<?php

namespace app\services;

use app\exceptions\PickPoint\LoginFailedException;
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

    /**
     * PickPointAPIService constructor.
     *
     * @throws \Exception
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
     * @throws \Exception
     */
    protected function sendRequest(string $url, array $content, string $method = 'POST'): Response
    {
        try {
            $response = $this->client->createRequest()
                ->setMethod($method)
                ->setUrl($url)
                ->addHeaders(['Content-Type' => 'application/json'])
                ->setContent(Json::encode($content))
                ->send();
        } catch (\Throwable $exception) {
            \Yii::error($exception->getMessage());
            throw new \Exception($exception->getMessage());
        }

        if (!$response->isOk) {
            \Yii::error($response->getContent());
            throw new \Exception($response->getContent(), $response->getStatusCode());
        }

        return $response;
    }

    /**
     * Обновить токен
     *
     * @throws \Exception
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
     * @throws LoginFailedException
     */
    protected function getSession(): string
    {
        $content = [
            'Login' => \Yii::$app->params['pickpoint']['login'] ?? '',
            'Password' => \Yii::$app->params['pickpoint']['password'] ?? '',
        ];

        $response = $this->sendRequest('/login', $content);

        if (!empty($response->data['ErrorMessage'])) {
            \Yii::error($response->data['ErrorMessage']);
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
     * @throws \Exception
     */
    public function createShipment(array $sendings): array
    {
        $content = [
            'SessionId' => $this->sessionId,
            'Sendings' => $sendings
        ];

        $response = $this->sendRequest('/CreateShipment', $content);

        if (!empty($response->data['ErrorCode'])) {
            throw new \Exception($response->data['Error']);
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
     * @return mixed
     *
     * @throws \Exception
     */
    public function createRegistry(array $sending)
    {
        $response = $this->sendRequest(
            '/makereestrnumber',
            array_merge(['SessionId' => $this->sessionId], $sending)
        );

        if (!empty($response->data['ErrorMessage'])) {
            throw new \Exception($response->data['ErrorMessage']);
        }

        return array_shift($response->data['Numbers']);
    }

    /**
     * Формирование этикеток в pdf
     *
     * @param array $invoices
     *
     * @return string
     *
     * @throws \Exception
     */
    public function makelabel(array $invoices): string
    {
        $content = [
            'SessionId' => $this->sessionId,
            'Invoices' => $invoices,
        ];

        // TODO: Пока формирую этикетоки pdf для принтера Zebra, обычное формирование этикеток в pdf не работает
        $response = $this->sendRequest('/makeZLabel', $content);

        if ('Error' === mb_substr($response->content, 0, 5) || '%PDF' !== mb_substr($response->content, 0, 4)) {
            throw new \Exception('Произошла ошибка при создании этикетки');
        }

        return $response->content;
    }
}