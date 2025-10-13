<?php
/**
 * SMS Sender Class
 * Deliverance Church Management System
 * 
 * Handles SMS sending via various providers (Africa's Talking, Twilio, etc.)
 */

class SMSSender {
    private $db;
    private $provider;
    private $config;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->provider = 'africastalking'; // Default provider
        $this->loadConfig();
    }
    
    /**
     * Load SMS configuration
     */
    private function loadConfig() {
        $this->config = [
            'africastalking' => [
                'username' => SMS_USERNAME ?? 'sandbox',
                'api_key' => SMS_API_KEY ?? '',
                'sender_id' => SMS_SENDER_ID ?? 'CHURCH',
                'endpoint' => 'https://api.africastalking.com/version1/messaging'
            ],
            'twilio' => [
                'account_sid' => '',
                'auth_token' => '',
                'from_number' => ''
            ],
            'custom' => [
                'endpoint' => '',
                'api_key' => '',
                'sender_id' => SMS_SENDER_ID ?? 'CHURCH'
            ]
        ];
    }
    
    /**
     * Process a batch of SMS messages
     * @param string $batchId
     * @return array
     */
    public function processBatch($batchId) {
        try {
            // Get pending messages for this batch
            $messages = $this->db->executeQuery("
                SELECT * FROM sms_individual 
                WHERE batch_id = ? AND status = 'pending'
                ORDER BY id
            ", [$batchId])->fetchAll();
            
            if (empty($messages)) {
                return [
                    'success' => false,
                    'message' => 'No pending messages found for this batch',
                    'sent_count' => 0,
                    'failed_count' => 0
                ];
            }
            
            // Update batch status to sending
            $this->db->executeQuery("
                UPDATE sms_history 
                SET status = 'sending', sent_at = NOW() 
                WHERE batch_id = ?
            ", [$batchId]);
            
            $sentCount = 0;
            $failedCount = 0;
            $errors = [];
            
            // Process messages in chunks
            $chunks = array_chunk($messages, 100); // Process 100 at a time
            
            foreach ($chunks as $chunk) {
                $result = $this->sendBulkSMS($chunk);
                
                $sentCount += $result['sent_count'];
                $failedCount += $result['failed_count'];
                
                if (!empty($result['errors'])) {
                    $errors = array_merge($errors, $result['errors']);
                }
                
                // Small delay between chunks to avoid rate limiting
                usleep(500000); // 0.5 second delay
            }
            
            // Update batch final status
            $finalStatus = $failedCount === 0 ? 'completed' : ($sentCount === 0 ? 'failed' : 'completed');
            
            $this->db->executeQuery("
                UPDATE sms_history 
                SET status = ?, sent_count = ?, failed_count = ?, completed_at = NOW()
                WHERE batch_id = ?
            ", [$finalStatus, $sentCount, $failedCount, $batchId]);
            
            // Update SMS balance
            if ($sentCount > 0) {
                $totalCost = $sentCount * SMS_COST_PER_SMS;
                $this->db->executeQuery("
                    UPDATE church_info 
                    SET sms_balance = sms_balance - ? 
                    WHERE id = 1
                ", [$totalCost]);
            }
            
            return [
                'success' => true,
                'message' => "Batch processed: {$sentCount} sent, {$failedCount} failed",
                'sent_count' => $sentCount,
                'failed_count' => $failedCount,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            error_log("SMS batch processing error: " . $e->getMessage());
            
            // Update batch status to failed
            $this->db->executeQuery("
                UPDATE sms_history 
                SET status = 'failed' 
                WHERE batch_id = ?
            ", [$batchId]);
            
            return [
                'success' => false,
                'message' => 'Error processing batch: ' . $e->getMessage(),
                'sent_count' => 0,
                'failed_count' => count($messages ?? [])
            ];
        }
    }
    
    /**
     * Send bulk SMS messages
     * @param array $messages
     * @return array
     */
    private function sendBulkSMS($messages) {
        $sentCount = 0;
        $failedCount = 0;
        $errors = [];
        
        switch ($this->provider) {
            case 'africastalking':
                return $this->sendViaAfricasTalking($messages);
                
            case 'twilio':
                return $this->sendViaTwilio($messages);
                
            case 'custom':
                return $this->sendViaCustomProvider($messages);
                
            default:
                // Fallback to simulation mode
                return $this->simulateSending($messages);
        }
    }
    
    /**
     * Send SMS via Africa's Talking API
     * @param array $messages
     * @return array
     */
    private function sendViaAfricasTalking($messages) {
        $config = $this->config['africastalking'];
        $sentCount = 0;
        $failedCount = 0;
        $errors = [];
        
        try {
            // Prepare recipients array
            $recipients = [];
            foreach ($messages as $message) {
                $recipients[] = [
                    'phoneNumber' => $message['recipient_phone'],
                    'message' => $message['message'],
                    'messageId' => $message['id']
                ];
            }
            
            // Prepare API request
            $postData = [
                'username' => $config['username'],
                'to' => implode(',', array_column($recipients, 'phoneNumber')),
                'message' => $messages[0]['message'], // For bulk, we'll send individual requests
                'from' => $config['sender_id']
            ];
            
            // Send individual messages (more reliable than bulk)
            foreach ($messages as $message) {
                $individualResult = $this->sendSingleSMS(
                    $message['recipient_phone'],
                    $message['message'],
                    $config
                );
                
                if ($individualResult['success']) {
                    $this->updateMessageStatus($message['id'], 'sent', $individualResult['provider_message_id']);
                    $sentCount++;
                } else {
                    $this->updateMessageStatus($message['id'], 'failed', null, $individualResult['error']);
                    $failedCount++;
                    $errors[] = "Failed to send to {$message['recipient_phone']}: {$individualResult['error']}";
                }
                
                // Small delay between individual sends
                usleep(100000); // 0.1 second
            }
            
        } catch (Exception $e) {
            error_log("Africa's Talking SMS error: " . $e->getMessage());
            
            // Mark all messages as failed
            foreach ($messages as $message) {
                $this->updateMessageStatus($message['id'], 'failed', null, $e->getMessage());
                $failedCount++;
            }
            
            $errors[] = "API Error: " . $e->getMessage();
        }
        
        return [
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'errors' => $errors
        ];
    }
    
    /**
     * Send single SMS via Africa's Talking
     * @param string $phone
     * @param string $message
     * @param array $config
     * @return array
     */
    private function sendSingleSMS($phone, $message, $config) {
        try {
            $postData = [
                'username' => $config['username'],
                'to' => $phone,
                'message' => $message,
                'from' => $config['sender_id']
            ];
            
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $config['endpoint'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($postData),
                CURLOPT_HTTPHEADER => [
                    'apiKey: ' . $config['api_key'],
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json'
                ],
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10
            ]);
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);
            
            if ($curlError) {
                throw new Exception("cURL Error: " . $curlError);
            }
            
            if ($httpCode !== 200) {
                throw new Exception("HTTP Error: " . $httpCode);
            }
            
            $responseData = json_decode($response, true);
            
            if (!$responseData || !isset($responseData['SMSMessageData'])) {
                throw new Exception("Invalid API response");
            }
            
            $smsData = $responseData['SMSMessageData'];
            
            if (isset($smsData['Recipients']) && !empty($smsData['Recipients'])) {
                $recipient = $smsData['Recipients'][0];
                
                if (isset($recipient['status']) && $recipient['status'] === 'Success') {
                    return [
                        'success' => true,
                        'provider_message_id' => $recipient['messageId'] ?? null,
                        'cost' => $recipient['cost'] ?? SMS_COST_PER_SMS
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => $recipient['status'] ?? 'Unknown error'
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'error' => $smsData['Message'] ?? 'No recipients processed'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Simulate SMS sending (for development/testing)
     * @param array $messages
     * @return array
     */
    private function simulateSending($messages) {
        $sentCount = 0;
        $failedCount = 0;
        
        foreach ($messages as $message) {
            // Simulate 95% success rate
            if (rand(1, 100) <= 95) {
                $this->updateMessageStatus($message['id'], 'sent', 'SIM_' . time() . rand(1000, 9999));
                $sentCount++;
            } else {
                $this->updateMessageStatus($message['id'], 'failed', null, 'Simulated failure');
                $failedCount++;
            }
            
            // Simulate processing delay
            usleep(10000); // 0.01 second
        }
        
        return [
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'errors' => []
        ];
    }
    
    /**
     * Send SMS via Twilio (placeholder for future implementation)
     * @param array $messages
     * @return array
     */
    private function sendViaTwilio($messages) {
        // TODO: Implement Twilio SMS sending
        return $this->simulateSending($messages);
    }
    
    /**
     * Send SMS via custom provider (placeholder)
     * @param array $messages
     * @return array
     */
    private function sendViaCustomProvider($messages) {
        // TODO: Implement custom SMS provider
        return $this->simulateSending($messages);
    }
    
    /**
     * Update individual message status
     * @param int $messageId
     * @param string $status
     * @param string|null $providerMessageId
     * @param string|null $errorMessage
     */
    private function updateMessageStatus($messageId, $status, $providerMessageId = null, $errorMessage = null) {
        $updateData = [
            'status' => $status,
            'error_message' => $errorMessage
        ];
        
        if ($status === 'sent') {
            $updateData['sent_at'] = date('Y-m-d H:i:s');
            $updateData['provider_message_id'] = $providerMessageId;
        }
        
        $setParts = [];
        $params = [];
        
        foreach ($updateData as $field => $value) {
            if ($value !== null) {
                $setParts[] = "$field = ?";
                $params[] = $value;
            }
        }
        
        $params[] = $messageId;
        
        $this->db->executeQuery("
            UPDATE sms_individual 
            SET " . implode(', ', $setParts) . "
            WHERE id = ?
        ", $params);
    }
    
    /**
     * Get SMS balance
     * @return float
     */
    public function getBalance() {
        $result = $this->db->executeQuery("SELECT sms_balance FROM church_info WHERE id = 1")->fetch();
        return $result ? (float)$result['sms_balance'] : 0.0;
    }
    
    /**
     * Add SMS balance
     * @param float $amount
     * @return bool
     */
    public function addBalance($amount) {
        try {
            $this->db->executeQuery("
                UPDATE church_info 
                SET sms_balance = sms_balance + ? 
                WHERE id = 1
            ", [$amount]);
            
            return true;
        } catch (Exception $e) {
            error_log("Error adding SMS balance: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get delivery status for messages (if supported by provider)
     * @param array $providerMessageIds
     * @return array
     */
    public function getDeliveryStatus($providerMessageIds) {
        // TODO: Implement delivery status checking
        // This would query the SMS provider for delivery confirmations
        return [];
    }
    
    /**
     * Resend failed messages
     * @param string $batchId
     * @return array
     */
    public function resendFailedMessages($batchId) {
        try {
            // Reset failed messages to pending
            $this->db->executeQuery("
                UPDATE sms_individual 
                SET status = 'pending', error_message = NULL, sent_at = NULL 
                WHERE batch_id = ? AND status = 'failed'
            ", [$batchId]);
            
            // Process the batch again
            return $this->processBatch($batchId);
            
        } catch (Exception $e) {
            error_log("Error resending failed messages: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error resending messages: ' . $e->getMessage(),
                'sent_count' => 0,
                'failed_count' => 0
            ];
        }
    }
    
    /**
     * Validate phone number format
     * @param string $phone
     * @return array
     */
    public function validatePhoneNumber($phone) {
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
        // Kenyan phone number validation
        if (preg_match('/^(\+254|254|0)[7-9]\d{8}$/', $phone)) {
            $formatted = formatPhoneNumber($phone);
            return [
                'valid' => true,
                'formatted' => $formatted,
                'country' => 'KE'
            ];
        }
        
        // International number (basic validation)
        if (preg_match('/^\+\d{10,15}$/', $phone)) {
            return [
                'valid' => true,
                'formatted' => $phone,
                'country' => 'INTL'
            ];
        }
        
        return [
            'valid' => false,
            'formatted' => $phone,
            'error' => 'Invalid phone number format'
        ];
    }
    
    /**
     * Get SMS sending statistics
     * @param string $period (today, week, month, year)
     * @return array
     */
    public function getStatistics($period = 'month') {
        $dateCondition = '';
        
        switch ($period) {
            case 'today':
                $dateCondition = "AND DATE(sent_at) = CURDATE()";
                break;
            case 'week':
                $dateCondition = "AND YEARWEEK(sent_at) = YEARWEEK(NOW())";
                break;
            case 'month':
                $dateCondition = "AND YEAR(sent_at) = YEAR(NOW()) AND MONTH(sent_at) = MONTH(NOW())";
                break;
            case 'year':
                $dateCondition = "AND YEAR(sent_at) = YEAR(NOW())";
                break;
        }
        
        $stats = $this->db->executeQuery("
            SELECT 
                COUNT(*) as total_messages,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(cost) as total_cost
            FROM sms_individual 
            WHERE 1=1 $dateCondition
        ")->fetch();
        
        return [
            'total_messages' => (int)$stats['total_messages'],
            'sent_count' => (int)$stats['sent_count'],
            'failed_count' => (int)$stats['failed_count'],
            'pending_count' => (int)$stats['pending_count'],
            'total_cost' => (float)$stats['total_cost'],
            'success_rate' => $stats['total_messages'] > 0 ? 
                round(($stats['sent_count'] / $stats['total_messages']) * 100, 2) : 0
        ];
    }
}
?>