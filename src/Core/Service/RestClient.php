<?php

namespace Vobapay\Payment\Core\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use OxidEsales\Eshop\Core\Registry;
use Vobapay\Payment\Core\Enum\VpHttpStatus;
use Vobapay\Payment\Helper\Payment as PaymentHelper;

class RestClient
{
    private Client $client;
    private LoggerInterface $logger;
    private string $apiUrl;
    private string $apiKey;

    /**
     * RestClient constructor.
     * Initializes API credentials and HTTP client.
     */
    public function __construct(Client $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;

        $this->apiKey = PaymentHelper::getInstance()->getVobapayApiKey();
        $this->apiUrl = PaymentHelper::getInstance()->getVobapayApiUrl();
    }

    /**
     * Creates a new payment.
     *
     * @param array $paymentData Payment request payload.
     * @return array Response containing payment UUID, status, and redirect details.
     */
    public function createPayment(array $paymentData): array
    {
        $sEndPoint = PaymentHelper::getInstance()->getValidUrl($this->apiUrl, "api/v2/payments");
        $response = $this->request('POST', $sEndPoint, $paymentData);

        return [
            'payment_uuid' => $response['data']['payment_uuid'] ?? null,
            'status' => $response['data']['status'] ?? null,
            'redirect_url' => $response['data']['redirect']['url'] ?? null,
            'redirect_method' => $response['data']['redirect']['method'] ?? null,
            'status_code' => $response['status_code'],
            'error' => $response['error'] ?? '',
        ];
    }

    /**
     * Sends an HTTP request to the API endpoint.
     *
     * @param string $method HTTP method (GET, POST, etc.).
     * @param string $endpoint API endpoint URL.
     * @param array $data Request payload.
     * @return array Decoded JSON response or error details.
     */
    private function request(string $method, string $endpoint, array $data = []): array
    {
        try {

            // Ensure API Key is set before making the request
            if (empty($this->apiKey)) {
                return [
                    'error' => Registry::getLang()->translateString('VOBAPAY_ERROR_MISSING_API_KEY'),
                    'status_code' => -1,
                ];
            }

            $aOptions = [
                'headers' => [
                    'accept' => 'application/json',
                    'content-type' => 'application/json',
                    'x-api-key' => $this->apiKey,
                ],
            ];

            if (!empty($data)) {
                $aOptions['json'] = $data;
            }

            $this->logger->error("RestClient REQUEST: " . print_r($data, true));

            $response = $this->client->request($method, $endpoint, $aOptions);
            $sStatusCode = $response->getStatusCode();
            $sContents = $response->getBody()->getContents();
            $aDataResponse = json_decode($sContents, true);

            $this->logger->error("RestClient RESPONSE: $sContents");

            // Handle different status codes
            if ($sStatusCode == VpHttpStatus::OK || $sStatusCode == VpHttpStatus::CREATED) {
                // Success responses (200, 201)
                return [
                    'data' => $aDataResponse,
                    'status_code' => $response->getStatusCode(),
                ];
            }

            // Handle known error cases
            $sErrorMessage = $aDataResponse['message'] ?? 'Unknown API error';

            switch ($sStatusCode) {
                case VpHttpStatus::BAD_REQUEST:
                    $sErrorMessage = RestClient . phpRegistry::getLang()->translateString('VOBAPAY_ERROR_400') . $sErrorMessage;
                    break;
                case VpHttpStatus::UNAUTHORIZED:
                    $sErrorMessage = Registry::getLang()->translateString('VOBAPAY_ERROR_401');
                    break;
                case VpHttpStatus::FORBIDDEN:
                    $sErrorMessage = Registry::getLang()->translateString('VOBAPAY_ERROR_403');
                    break;
                case VpHttpStatus::NOT_FOUND:
                    $sErrorMessage = Registry::getLang()->translateString('VOBAPAY_ERROR_404');
                    break;
                case VpHttpStatus::INTERNAL_SERVER_ERROR:
                    $sErrorMessage = Registry::getLang()->translateString('VOBAPAY_ERROR_500');
                    break;
            }

            return [
                'error' => $sErrorMessage,
                'status_code' => $sStatusCode,
            ];
        } catch (RequestException|GuzzleException $e) {
            $response = $e->getResponse();
            $responseBody = json_decode($response->getBody()->getContents(), true);
            $sErrorMessage = $responseBody['message'] ?? $e->getMessage();

            $this->logger->error('Vobapay API error: ' . $e->getMessage() );
            $this->logger->error('Vobapay API error: ' . print_r($responseBody, true) );

            return [
                'error' => $sErrorMessage,
                'status_code' => $e->getCode(),
            ];
        }
    }

    /**
     * Retrieves payment details by payment ID.
     *
     * @param string $paymenId Payment UUID.
     * @return array Payment details including status, amounts, and transactions.
     */
    public function getPayment(string $paymenId): array
    {
        $sEndPoint = PaymentHelper::getInstance()->getValidUrl($this->apiUrl, "api/v2/payments/$paymenId");
        $response = $this->request('GET', $sEndPoint, []);

        return [
            'payment_uuid' => $response['data']['payment_uuid'] ?? null,
            'status' => $response['data']['status'] ?? null,
            'status_code' => $response['status_code'] ?? '',
            'amount' => $response['data']['amount'] ?? [],
            'authorized' => $response['data']['authorized'] ?? [],
            'captured' => $response['data']['captured'] ?? [],
            'refunded' => $response['data']['refunded'] ?? [],
            'capture' => $response['data']['capture'] ?? [],
            'refund' => $response['data']['refund'] ?? [],
            'transactions' => $response['data']['transactions']['items'] ?? [],
            'error' => $response['error'] ?? '',
        ];
    }

    /**
     * Initiates a refund for a given payment ID.
     *
     * @param string $paymenId Payment UUID.
     * @param array $paymentData Refund request payload.
     * @return array API response.
     */
    public function refundPayment(string $paymenId, array $paymentData): array
    {
        $sEndPoint = PaymentHelper::getInstance()->getValidUrl($this->apiUrl, "api/v2/payments/$paymenId/refund");
        $response = $this->request('POST', $sEndPoint, $paymentData);

        return [
            'payment_uuid' => $response['data']['payment_uuid'] ?? null,
            'status' => $response['data']['status'] ?? null,
            'refund_status' => $response['data']['refund_status'] ?? null,
            'status_code' => $response['status_code'],
            'error' => $response['error'] ?? '',
        ];
    }

    /**
     * Captures a previously authorized payment.
     *
     * @param string $paymenId Payment UUID.
     * @param array $paymentData Capture request payload.
     * @return array API response.
     */
    public function capturePayment(string $paymenId, array $paymentData): array
    {
        $sEndPoint = PaymentHelper::getInstance()->getValidUrl($this->apiUrl, "api/v2/payments/$paymenId/capture");
        $response = $this->request('POST', $sEndPoint, $paymentData);

        return [
            'payment_uuid' => $response['data']['payment_uuid'] ?? null,
            'status' => $response['data']['status'] ?? null,
            'capture_status' => $response['data']['capture_status'] ?? null,
            'status_code' => $response['status_code'],
            'error' => $response['error'] ?? '',
        ];
    }
}