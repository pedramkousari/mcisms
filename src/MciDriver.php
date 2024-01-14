<?php

namespace Pedramkousari\Mcisms;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Pedramkousari\Sms\Contracts\DriverMultipleContactsInterface;
use Pedramkousari\Sms\Drivers\Driver;
use Pedramkousari\Sms\SmsDriverResponse;

class MciDriver extends Driver implements DriverMultipleContactsInterface
{
    protected Client $client;
    private string $baseUrl;

    private string $username;

    private string $password;

    private string $client_id;

    private string $grant_type;

    private string $token;

    /**
     * Type message
     * @var string
     */
    protected $type = SmsType::NORMAL;
    private string $source;

    public function __construct($config)
    {
        $this->client = new Client();

        $this->baseUrl = $config["url"];
        $this->username = $config["username"];
        $this->password = $config["password"];
        $this->grant_type = $config["password"];
        $this->client_id = $config["client_id"];
        $this->source = $config["source"];
        $this->token = cache('mci_token');
    }

    public function send(): SmsDriverResponse
    {
        if (empty($this->token)) {
            $this->login();
        }

        $recipients = collect(Arr::wrap($this->getTo()))->map(function ($recipient) {
            return [
                'mobileNo' => $recipient
            ];
        });


        $raw_response = $this->client->post(
            $this->getSendSmsApiUrl(),
            [
                "destinationList" => $recipients->toJson(),
                "message" => $this->getMessage(),
                "smsClass" => $this->getType(),
                "source" => $this->source,
                'debug' => true,
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->token,
                ],
            ]
        );


        $response = json_decode($raw_response->getBody()->getContents());
        $succeeded = true;
        foreach ($response['smsIdList'] as $smsId) {
            $code = (int)$smsId;
            if ($code >= 1 && $code <= 255) {
                $succeeded = false;
                break;
            }
        }
        return new SmsDriverResponse($recipients->toArray(), $response, $succeeded);
    }

    private function login(): void
    {
        $this->validateConfiguration();
        $raw_response = $this->client->request('POST', $this->getLoginApiUrl(), [
            'form_params' => [
                'grant_type' => $this->grant_type,
                'client_id' => $this->client_id
            ],
            'auth' => [
                $this->username,
                $this->password,
            ]
        ]);

//        $raw_response->getStatusCode()

        $response = json_decode($raw_response->getBody()->getContents());

        $token = $response['access_token'];

        cache([
            'mci_token' => $token,
        ], now()->addMinutes($response['expires_in']));

        $this->token = $token;
    }

    private function validateConfiguration()
    {
        if (empty($this->baseUrl)) {
            throw new Exception('mci config not found: api base url');
        }

        if (empty($this->username)) {
            throw new Exception('mci config not found: username');
        }

        if (empty($this->password)) {
            throw new Exception('mci config not found: password');
        }
    }

    private function getHttpHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
    }

    private function getLoginApiUrl(): string
    {
        return $this->baseUrl . '/auth/realms/WebService/protocol/openid-connect/token';
    }

    private function getSendSmsApiUrl(): string
    {
        return $this->baseUrl . '/bulk-api/api/v2/sendBulk';
    }

    private function getErrors() :array
    {
        return [
            9 => 'TPS Violation, Maximum TPS of user is violated',
            10 => 'Invalid Source Address, Shortcode is not valid',
            11 => 'Invalid Destination Address, Destination mobile number format is not valid',
            16 => 'User Active Status violation, User is not active',
            18 => 'Shortcode Registration Violation, Shortcode is not registered in Shahkar',
            20 => 'TPS is not Assigned to User',
            22 => 'Queue Overflow (Try again after a while)',
            41 => 'Credit Violation, User does not have enough credit',
            42 => 'Internal Error in Updating User Credit',
            65 => 'Shortcode Access Violation, User does not have access to shortcode',
            67 => 'SMS Class Access Violation, User does not have access to SMS class',
            70 => 'SMS DCS Access Violation, User does not have access to DCS',
            98 => 'Social Hour Violation, Shortcode does not have access to send SMS at the requested time',
            104 => 'Invalid Base64 Format in Sending Binary SMS'
        ];
    }
}
