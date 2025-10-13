<?php
/**
 * WhatsApp Sender Class
 * Deliverance Church Management System
 * 
 * Handles WhatsApp message sending via WhatsApp Business API
 * Supports individual messages and group messages
 */

class WhatsAppSender {
    private $db;
    private $config;
    private $businessPhone = '254745600377'; // Your WhatsApp Business number
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->loadConfig();
    }
    
    /**
     * Load WhatsApp configuration
     */
    private function loadConfig() {
        $this->config = [
            'api_url' => 'https://graph.facebook.com/v18.0', // WhatsApp Business API
            'access_token' => '', // Your WhatsApp Business API access token
            'phone_number_id' => '', // Your WhatsApp Business phone number ID
            'business_account_id' => '',
            'webhook_verify_token' => 'CHURCH_CMS_WEBHOOK_TOKEN_' . md5(ENCRYPTION_KEY)
        ];
        
        // Try to load from database settings
        try {
            $settings = $this->db->executeQuery("
                SELECT setting_key, setting_value 
                FROM system_settings 
                WHERE setting_key LIKE 'whatsapp_%'
            ")->fetchAll();
            
            foreach ($settings as $setting) {
                $key = str_replace('whatsapp_', '', $setting['setting_key']);
                if (isset($this->config[$key])) {
                    $this->config[$key] = $setting['setting_value'];
                }
            }
        } catch (Exception $e) {
            error_log("Error loading WhatsApp config: " . $e->getMessage());
        }
    }
    
    /**
     * Send WhatsApp message to individual or multiple recipients
     * @param array $recipients
     * @param string $message
     * @param array $options
     * @return array
     */
    public function sendMessage($recipients, $message, $options = []) {
        $results = [
            'success' => true,
            'sent_count' => 0,
            'failed_count' => 0,
            'results' => []
        ];
        
        foreach ($recipients as $recipient) {
            $phone = $this->formatPhoneNumber($recipient['phone']);
            $personalizedMessage = $this->personalizeMessage($message, $recipient);
            
            $result = $this->sendSingleMessage($phone, $personalizedMessage, $options);
            
            if ($result['success']) {
                $results['sent_count']++;
            } else {
                $results['failed_count']++;
            }
            
            $results['results'][] = [
                'phone' => $phone,
                'name' => $recipient['name'] ?? '',
                'success' => $result['success'],
                'message_id' => $result['message_id'] ?? null,
                'error' => $result['error'] ?? null
            ];
            
            // Small delay to avoid rate limiting
            usleep(500000); // 0.5 second
        }
        
        if ($results['failed_count'] > 0) {
            $results['success'] = false;
        }
        
        return $results;
    }
    
    /**
     * Send single WhatsApp message
     * @param string $phone
     * @param string $message
     * @param array $options
     * @return array
     */
    private function sendSingleMessage($phone, $message, $options = []) {
        try {
            // Check if using simulation mode (for development)
            if (empty($this->config['access_token']) || empty($this->config['phone_number_id'])) {
                return $this->simulateSend($phone, $message);
            }
            
            $url = $this->config['api_url'] . '/' . $this->config['phone_number_id'] . '/messages';
            
            $data = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $phone,
                'type' => 'text',
                'text' => [
                    'preview_url' => isset($options['preview_url']) ? $options['preview_url'] : false,
                    'body' => $message
                ]
            ];
            
            // Add media if provided
            if (isset($options['media'])) {
                $data['type'] = $options['media']['type']; // image, video, document
                $data[$options['media']['type']] = [
                    'link' => $options['media']['url'],
                    'caption' => $options['media']['caption'] ?? ''
                ];
                unset($data['text']);
            }
            
            $response = $this->makeAPIRequest($url, $data);
            
            if ($response['success']) {
                return [
                    'success' => true,
                    'message_id' => $response['data']['messages'][0]['id'] ?? null
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response['error'] ?? 'Unknown error'
                ];
            }
            
        } catch (Exception $e) {
            error_log("WhatsApp send error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send message to WhatsApp group
     * @param string $groupId
     * @param string $message
     * @param array $options
     * @return array
     */
    public function sendGroupMessage($groupId, $message, $options = []) {
        try {
            // Note: Direct group messaging via API requires WhatsApp Business API with group permissions
            // Alternative: Send to group admin who can forward to group
            
            $url = $this->config['api_url'] . '/' . $this->config['phone_number_id'] . '/messages';
            
            $data = [
                'messaging_product' => 'whatsapp',
                'to' => $groupId,
                'type' => 'text',
                'text' => [
                    'body' => $message
                ]
            ];
            
            $response = $this->makeAPIRequest($url, $data);
            
            if ($response['success']) {
                return [
                    'success' => true,
                    'message_id' => $response['data']['messages'][0]['id'] ?? null
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response['error'] ?? 'Failed to send to group'
                ];
            }
            
        } catch (Exception $e) {
            error_log("WhatsApp group send error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send template message (for approved templates)
     * @param string $phone
     * @param string $templateName
     * @param array $parameters
     * @return array
     */
    public function sendTemplateMessage($phone, $templateName, $parameters = []) {
        try {
            $phone = $this->formatPhoneNumber($phone);
            $url = $this->config['api_url'] . '/' . $this->config['phone_number_id'] . '/messages';
            
            $components = [];
            if (!empty($parameters)) {
                $components[] = [
                    'type' => 'body',
                    'parameters' => array_map(function($param) {
                        return ['type' => 'text', 'text' => $param];
                    }, $parameters)
                ];
            }
            
            $data = [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => [
                        'code' => 'en'
                    ],
                    'components' => $components
                ]
            ];
            
            $response = $this->makeAPIRequest($url, $data);
            
            if ($response['success']) {
                return [
                    'success' => true,
                    'message_id' => $response['data']['messages'][0]['id'] ?? null
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response['error'] ?? 'Template send failed'
                ];
            }
            
        } catch (Exception $e) {
            error_log("WhatsApp template send error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send media message (image, video, document)
     * @param string $phone
     * @param string $mediaType
     * @param string $mediaUrl
     * @param string $caption
     * @return array
     */
    public function sendMediaMessage($phone, $mediaType, $mediaUrl, $caption = '') {
        try {
            $phone = $this->formatPhoneNumber($phone);
            $url = $this->config['api_url'] . '/' . $this->config['phone_number_id'] . '/messages';
            
            $data = [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => $mediaType,
                $mediaType => [
                    'link' => $mediaUrl
                ]
            ];
            
            if (!empty($caption) && in_array($mediaType, ['image', 'video', 'document'])) {
                $data[$mediaType]['caption'] = $caption;
            }
            
            $response = $this->makeAPIRequest($url, $data);
            
            if ($response['success']) {
                return [
                    'success' => true,
                    'message_id' => $response['data']['messages'][0]['id'] ?? null
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response['error'] ?? 'Media send failed'
                ];
            }
            
        } catch (Exception $e) {
            error_log("WhatsApp media send error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Make API request to WhatsApp Business API
     * @param string $url
     * @param array $data
     * @return array
     */
    private function makeAPIRequest($url, $data) {
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->config['access_token'],
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);
        
        if ($curlError) {
            return [
                'success' => false,
                'error' => "cURL Error: " . $curlError
            ];
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $responseData
            ];
        } else {
            return [
                'success' => false,
                'error' => $responseData['error']['message'] ?? 'API request failed',
                'error_code' => $responseData['error']['code'] ?? $httpCode
            ];
        }
    }
    
    /**
     * Simulate WhatsApp sending (for development)
     * @param string $phone
     * @param string $message
     * @return array
     */
    private function simulateSend($phone, $message) {
        // Log simulated send
        error_log("WhatsApp SIMULATION: Sending to $phone: $message");
        
        // Simulate 98% success rate
        $success = rand(1, 100) <= 98;
        
        if ($success) {
            return [
                'success' => true,
                'message_id' => 'wamid.SIM_' . time() . rand(1000, 9999)
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Simulated failure'
            ];
        }
    }
    
    /**
     * Format phone number for WhatsApp (international format without +)
     * @param string $phone
     * @return string
     */
    private function formatPhoneNumber($phone) {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
        // Remove leading + if present
        $phone = ltrim($phone, '+');
        
        // Convert Kenyan numbers to international format
        if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        } elseif (strlen($phone) === 9) {
            $phone = '254' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Personalize message with member data
     * @param string $message
     * @param array $recipient
     * @return string
     */
    private function personalizeMessage($message, $recipient) {
        $replacements = [
            '{first_name}' => $recipient['first_name'] ?? $recipient['name'] ?? 'Member',
            '{last_name}' => $recipient['last_name'] ?? '',
            '{full_name}' => ($recipient['first_name'] ?? '') . ' ' . ($recipient['last_name'] ?? ''),
            '{phone}' => $recipient['phone'] ?? '',
            '{church_name}' => 'Deliverance Church'
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }
    
    /**
     * Get WhatsApp groups (if configured)
     * @return array
     */
    public function getGroups() {
        try {
            $groups = $this->db->executeQuery("
                SELECT * FROM whatsapp_groups 
                WHERE is_active = 1 
                ORDER BY name
            ")->fetchAll();
            
            return $groups;
        } catch (Exception $e) {
            error_log("Error getting WhatsApp groups: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Save WhatsApp group
     * @param array $groupData
     * @return int|false
     */
    public function saveGroup($groupData) {
        try {
            return insertRecord('whatsapp_groups', $groupData);
        } catch (Exception $e) {
            error_log("Error saving WhatsApp group: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get message delivery status
     * @param string $messageId
     * @return array
     */
    public function getMessageStatus($messageId) {
        try {
            $url = $this->config['api_url'] . '/' . $messageId;
            
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->config['access_token']
                ]
            ]);
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            
            if ($httpCode === 200) {
                return json_decode($response, true);
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Error getting message status: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Validate WhatsApp number
     * @param string $phone
     * @return array
     */
    public function validateNumber($phone) {
        $formatted = $this->formatPhoneNumber($phone);
        
        // Basic validation
        if (strlen($formatted) < 10 || strlen($formatted) > 15) {
            return [
                'valid' => false,
                'error' => 'Invalid phone number length'
            ];
        }
        
        return [
            'valid' => true,
            'formatted' => $formatted
        ];
    }
    
    /**
     * Get WhatsApp Business profile
     * @return array
     */
    public function getBusinessProfile() {
        try {
            $url = $this->config['api_url'] . '/' . $this->config['phone_number_id'] . '/whatsapp_business_profile';
            
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->config['access_token']
                ]
            ]);
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            
            if ($httpCode === 200) {
                return json_decode($response, true);
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Error getting business profile: " . $e->getMessage());
            return null;
        }
    }
}
?>