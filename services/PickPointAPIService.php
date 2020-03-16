<?php

namespace app\services;

use app\models\Token;
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

        $token = Token::findOne(['service' => self::class]);

        // ЕСЛИ токен пустой ИЛИ текущее время больше времени жизни токена ТО перевыпустить
        if (empty($token) ||
            (new \DateTime) > (new \DateTime($token->issued_at))->add(new \DateInterval('P1D'))) {
            $this->refresh();
        } else {
            $this->sessionId = $token->session_id;
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
        $token = Token::findOne(['service' => self::class]);

        $this->sessionId = $this->getSession();

        if (empty($token)) {
            $token = new Token;
            $token->service = self::class;
        }

        $token->session_id = $this->sessionId;
        $token->issued_at = date('Y-m-d H:i:s');

        $token->save();
    }

    /**
     * Получить номер сессии для дальнейшей работы в остальных методах
     *
     * @return string
     *
     * @throws \Exception
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
            throw new \Exception($response->data['ErrorMessage']);
        }

        return $response->data['SessionId'];
    }

    /**
     * Регистрация отправления
     *
     * @param array $sending
     *
     * @return array
     *
     * @throws \Exception
     */
    public function createShipment(array $sending): array
    {
        $content = [
            'SessionId' => $this->sessionId,
            'Sendings' => [
                $sending,
            ]
        ];

        $response = $this->sendRequest('/login', $content);

        if (!empty($response->data['ErrorMessage'])) {
            throw new \Exception($response->data['ErrorMessage']);
        }

        return array_shift($response->data['CreatedSendings']);
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

        $response = $this->sendRequest('/makereestrnumber', $content);

        if ('Error' === mb_substr($response->content, 0, 5) || '%PDF' !== mb_substr($response->content, 0, 4)) {
            throw new \Exception('Произошла ошибка при создании этикетки');
        }

        return $response->content;
    }
}