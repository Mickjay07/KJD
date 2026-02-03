<?php
/**
 * Packeta (Zásilkovna) API Client
 * Documentation: https://docs.packetery.com/
 */

require_once __DIR__ . '/config.php';

class PacketaAPI {
    private $apiKey;
    private $apiPassword;
    private $apiUrl = 'https://www.zasilkovna.cz/api/rest';
    
    public function __construct() {
        $this->apiKey = PACKETA_API_KEY;
        $this->apiPassword = PACKETA_API_PASSWORD;
    }
    
    /**
     * Create a new packet
     * 
     * @param array $packetData Packet information
     * @return array Result with packet ID or error
     */
    public function createPacket($packetData) {
        $url = $this->apiUrl;
        
        // Build XML request
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><createPacket/>');
        $xml->addAttribute('apiPassword', $this->apiPassword);
        
        // Add packet attributes
        $packetAttributes = [
            'number' => $packetData['order_number'],
            'name' => $packetData['name'],
            'surname' => $packetData['surname'],
            'email' => $packetData['email'],
            'phone' => $packetData['phone'],
            'addressId' => $packetData['branch_id'],
            'cod' => $packetData['cod'] ?? 0,
            'value' => $packetData['value'],
            'weight' => $packetData['weight'] ?? 1,
            'eshop' => $this->apiKey
        ];
        
        foreach ($packetAttributes as $key => $value) {
            $xml->addAttribute($key, $value);
        }
        
        // Send request
        $result = $this->sendRequest($xml->asXML());
        
        if ($result['success']) {
            return [
                'success' => true,
                'packet_id' => (string)$result['response']->result->id,
                'barcode' => (string)$result['response']->result->barcode
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['error']
        ];
    }
    
    /**
     * Get packet label PDF
     * 
     * @param string $packetId Packet ID
     * @param string $format PDF format (A7 or A6)
     * @return array Result with PDF content or error
     */
    public function getPacketLabel($packetId, $format = 'A7') {
        $url = "https://www.zasilkovna.cz/api/packet-label";
        
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><packetLabelPdf/>');
        $xml->addAttribute('apiPassword', $this->apiPassword);
        $xml->addAttribute('format', $format);
        
        $packetIds = $xml->addChild('packetIds');
        $packetIds->addChild('id', $packetId);
        
        $result = $this->sendRequest($xml->asXML(), $url);
        
        if ($result['success']) {
            return [
                'success' => true,
                'pdf' => base64_decode((string)$result['response']->result->pdf)
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['error']
        ];
    }
    
    /**
     * Get tracking URL for packet
     * 
     * @param string $barcode Packet barcode
     * @return string Tracking URL
     */
    public function getTrackingUrl($barcode) {
        return "https://tracking.packeta.com/cs/?id=" . $barcode;
    }
    
    /**
     * Send XML request to API
     * 
     * @param string $xmlData XML request data
     * @param string $url API endpoint URL
     * @return array Response or error
     */
    private function sendRequest($xmlData, $url = null) {
        if ($url === null) {
            $url = $this->apiUrl;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: text/xml; charset=utf-8'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("Packeta API Error: " . $error);
            return [
                'success' => false,
                'error' => 'Chyba komunikace s API: ' . $error
            ];
        }
        
        if ($httpCode !== 200) {
            error_log("Packeta API HTTP Error: " . $httpCode);
            return [
                'success' => false,
                'error' => 'HTTP chyba: ' . $httpCode,
                'raw_response' => $response
            ];
        }
        
        // Log raw response to file for debugging
        file_put_contents('debug_packeta_response.log', date('Y-m-d H:i:s') . " RESPONSE: " . $response . "\n", FILE_APPEND);
        
        try {
            $xml = new SimpleXMLElement($response);
            
            // Log raw response for debugging
            error_log("Packeta API Raw Response: " . $response);

            // Check for API errors
            if (isset($xml->fault)) {
                // Try different fault fields
                $errorMsg = '';
                if (isset($xml->fault->faultString)) {
                    $errorMsg = (string)$xml->fault->faultString;
                } elseif (isset($xml->fault->string)) {
                    $errorMsg = (string)$xml->fault->string;
                } elseif (isset($xml->fault->message)) {
                    $errorMsg = (string)$xml->fault->message;
                } else {
                    // Try to cast the whole fault element to string
                    $errorMsg = (string)$xml->fault;
                }
                
                error_log("Packeta API Fault: " . $errorMsg);
                return [
                    'success' => false,
                    'error' => 'API FAULT: ' . ($errorMsg ?: 'Unknown Fault'),
                    'raw_response' => $response
                ];
            }
            
            // Check for status error
            if (isset($xml->status) && (string)$xml->status === 'error') {
                $errorMsg = (string)$xml->result->message;
                if (empty($errorMsg)) {
                    $errorMsg = "API returned error status but no message";
                }
                error_log("Packeta API Error Status: " . $errorMsg);
                return [
                    'success' => false,
                    'error' => 'API ERROR: ' . $errorMsg,
                    'raw_response' => $response
                ];
            }
            
            return [
                'success' => true,
                'response' => $xml
            ];
            
        } catch (Exception $e) {
            error_log("Packeta API Parse Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Chyba zpracování odpovědi: ' . $e->getMessage(),
                'raw_response' => $response
            ];
        }
    }
    
    /**
     * Get list of pickup points (branches)
     * This uses the public widget API, separate from the private API
     * 
     * @return array|false List of branches or false on error
     */
    public function getBranches() {
        $url = "https://www.zasilkovna.cz/api/v4/" . $this->apiKey . "/branch.json";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            return json_decode($response, true);
        }
        
        return false;
    }
}
