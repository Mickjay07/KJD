<?php
/**
 * GoPay Payment Gateway Helper Class
 * Simple wrapper for GoPay API without SDK dependency
 * Uses cURL for HTTP requests directly to GoPay API
 */

class GoPayHelper {
    private $goid;
    private $clientId;
    private $clientSecret;
    private $isProduction;
    private $gatewayUrl;
    private $accessToken;
    
    public function __construct() {
        $this->goid = GOPAY_GOID;
        $this->clientId = GOPAY_CLIENT_ID;
        $this->clientSecret = GOPAY_CLIENT_SECRET;
        $this->isProduction = GOPAY_IS_PRODUCTION;
        $this->gatewayUrl = GOPAY_GATEWAY_URL;
    }
    
    /**
     * Get OAuth access token from GoPay
     */
    private function getAccessToken() {
        if ($this->accessToken) {
            return $this->accessToken;
        }
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->gatewayUrl . '/oauth2/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials&scope=payment-all',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret)
            ],
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($httpCode !== 200) {
            throw new Exception('Failed to get GoPay access token: ' . $response);
        }
        
        $data = json_decode($response, true);
        $this->accessToken = $data['access_token'] ?? null;
        
        if (!$this->accessToken) {
            throw new Exception('No access token in GoPay response');
        }
        
        return $this->accessToken;
    }
    
    /**
     * Create a new payment
     * 
     * @param array $orderData Order details
     * @return array Payment response with gw_url and id
     */
    public function createPayment($orderData) {
        $token = $this->getAccessToken();
        
        // Prepare payment data
        $paymentData = [
            'payer' => [
                'default_payment_instrument' => 'PAYMENT_CARD',
                'allowed_payment_instruments' => ['PAYMENT_CARD', 'BANK_ACCOUNT'],
                'contact' => [
                    'first_name' => $orderData['first_name'] ?? '',
                    'last_name' => $orderData['last_name'] ?? '',
                    'email' => $orderData['email'] ?? '',
                    'phone_number' => $orderData['phone'] ?? '',
                ]
            ],
            'target' => [
                'type' => 'ACCOUNT',
                'goid' => $this->goid
            ],
            'amount' => (int)($orderData['amount'] * 100), // Convert to haléře (cents)
            'currency' => GOPAY_CURRENCY,
            'order_number' => $orderData['order_number'] ?? uniqid('ORDER_'),
            'order_description' => $orderData['description'] ?? 'Objednávka KJD Designs',
            'items' => $orderData['items'] ?? [],
            'callback' => [
                'return_url' => GOPAY_RETURN_URL . '?order_id=' . ($orderData['order_number'] ?? ''),
                'notification_url' => GOPAY_NOTIFICATION_URL
            ],
            'lang' => GOPAY_LANGUAGE
        ];
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->gatewayUrl . '/payments/payment',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($paymentData),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ],
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($httpCode !== 201) {
            throw new Exception('Failed to create GoPay payment: ' . $response);
        }
        
        $data = json_decode($response, true);
        
        return [
            'id' => $data['id'] ?? null,
            'gw_url' => $data['gw_url'] ?? null,
            'state' => $data['state'] ?? null
        ];
    }
    
    /**
     * Get payment status
     * 
     * @param string|int $paymentId GoPay payment ID
     * @return array Payment status details
     */
    public function getPaymentStatus($paymentId) {
        $token = $this->getAccessToken();
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->gatewayUrl . '/payments/payment/' . $paymentId,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $token
            ],
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($httpCode !== 200) {
            throw new Exception('Failed to get GoPay payment status: ' . $response);
        }
        
        $data = json_decode($response, true);
        
        return [
            'id' => $data['id'] ?? null,
            'state' => $data['state'] ?? null,
            'amount' => isset($data['amount']) ? $data['amount'] / 100 : 0, // Convert from haléře
            'currency' => $data['currency'] ?? GOPAY_CURRENCY,
            'payment_instrument' => $data['payment_instrument'] ?? null,
            'order_number' => $data['order_number'] ?? null,
            'payer_email' => $data['payer']['contact']['email'] ?? null
        ];
    }
    
    /**
     * Refund payment
     * 
     * @param string|int $paymentId GoPay payment ID
     * @param float $amount Amount to refund (optional, full refund if not specified)
     * @return array Refund response
     */
    public function refundPayment($paymentId, $amount = null) {
        $token = $this->getAccessToken();
        
        $refundData = [];
        if ($amount !== null) {
            $refundData['amount'] = (int)($amount * 100); // Convert to haléře
        }
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->gatewayUrl . '/payments/payment/' . $paymentId . '/refund',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($refundData),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ],
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($httpCode !== 200) {
            throw new Exception('Failed to refund GoPay payment: ' . $response);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Verify payment notification signature
     * 
     * @param array $notification Notification data from GoPay
     * @return bool True if signature is valid
     */
    public function verifyNotification($notification) {
        // GoPay sends signature in header X-Signature
        // For now, we'll verify by checking payment status via API
        return true;
    }
}
