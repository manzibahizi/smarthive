<?php

namespace App\Services;

class NotificationService
{
    private $config;
    
    public function __construct()
    {
        $this->config = [
            'smtp_host' => $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com',
            'smtp_port' => $_ENV['SMTP_PORT'] ?? 587,
            'smtp_username' => $_ENV['SMTP_USERNAME'] ?? '',
            'smtp_password' => $_ENV['SMTP_PASSWORD'] ?? '',
            'smtp_from_email' => $_ENV['SMTP_FROM_EMAIL'] ?? 'alerts@smarthivesolution.com',
            'smtp_from_name' => $_ENV['SMTP_FROM_NAME'] ?? 'Smart Hive Solution',
            'sms_api_key' => $_ENV['SMS_API_KEY'] ?? '',
            'sms_api_url' => $_ENV['SMS_API_URL'] ?? 'https://api.twilio.com/2010-04-01/Accounts/{account_sid}/Messages.json',
            'sms_from_number' => $_ENV['SMS_FROM_NUMBER'] ?? '+1234567890'
        ];
    }
    
    /**
     * Send email notification
     */
    public function sendEmail($to, $subject, $message, $priority = 'normal')
    {
        if (empty($this->config['smtp_username']) || empty($this->config['smtp_password'])) {
            error_log("SMTP not configured, email not sent to: $to");
            return false;
        }
        
        try {
            $headers = [
                'From: ' . $this->config['smtp_from_name'] . ' <' . $this->config['smtp_from_email'] . '>',
                'Reply-To: ' . $this->config['smtp_from_email'],
                'Content-Type: text/html; charset=UTF-8',
                'X-Mailer: Smart Hive Solution Alert System'
            ];
            
            if ($priority === 'high') {
                $headers[] = 'X-Priority: 1';
                $headers[] = 'X-MSMail-Priority: High';
            }
            
            $fullMessage = $this->formatEmailMessage($subject, $message, $priority);
            
            return mail($to, $subject, $fullMessage, implode("\r\n", $headers));
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send SMS notification
     */
    public function sendSMS($phoneNumber, $message)
    {
        if (empty($this->config['sms_api_key'])) {
            error_log("SMS API not configured, SMS not sent to: $phoneNumber");
            return false;
        }
        
        // Clean phone number
        $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
        if (!preg_match('/^\+?[1-9]\d{1,14}$/', $phoneNumber)) {
            error_log("Invalid phone number format: $phoneNumber");
            return false;
        }
        
        try {
            $data = [
                'To' => $phoneNumber,
                'From' => $this->config['sms_from_number'],
                'Body' => $message
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->config['sms_api_url']);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, $this->config['sms_api_key']);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode >= 200 && $httpCode < 300;
        } catch (Exception $e) {
            error_log("SMS sending failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send alert to user with multiple channels
     */
    public function sendAlert($user, $alertType, $message, $priority = 'normal')
    {
        $results = [];
        
        // Send email if user has email
        if (!empty($user['email'])) {
            $subject = "Smart Hive Alert: " . ucfirst($alertType);
            $results['email'] = $this->sendEmail($user['email'], $subject, $message, $priority);
        }
        
        // Send SMS if user has phone and it's high priority
        if (!empty($user['phone']) && $priority === 'high') {
            $smsMessage = "🚨 Smart Hive Alert: " . ucfirst($alertType) . "\n" . $message;
            $results['sms'] = $this->sendSMS($user['phone'], $smsMessage);
        }
        
        return $results;
    }
    
    /**
     * Send bulk alerts to multiple users
     */
    public function sendBulkAlerts($users, $alertType, $message, $priority = 'normal')
    {
        $results = [];
        
        foreach ($users as $user) {
            $results[$user['id']] = $this->sendAlert($user, $alertType, $message, $priority);
        }
        
        return $results;
    }
    
    /**
     * Format email message with HTML template
     */
    private function formatEmailMessage($subject, $message, $priority)
    {
        $color = $priority === 'high' ? '#dc3545' : '#007bff';
        $icon = $priority === 'high' ? '🚨' : '📧';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>$subject</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: linear-gradient(135deg, $color, #0056b3); color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center;'>
                <h1 style='margin: 0; font-size: 24px;'>$icon Smart Hive Solution</h1>
                <p style='margin: 10px 0 0 0; opacity: 0.9;'>Automated Alert System</p>
            </div>
            
            <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; border: 1px solid #dee2e6;'>
                <h2 style='color: $color; margin-top: 0;'>$subject</h2>
                <div style='background: white; padding: 20px; border-radius: 6px; border-left: 4px solid $color; margin: 20px 0;'>
                    $message
                </div>
                
                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6;'>
                    <p style='font-size: 14px; color: #6c757d; margin: 0;'>
                        This is an automated message from Smart Hive Solution.<br>
                        Please check your dashboard for more details: <a href='http://localhost:8000' style='color: $color;'>Smart Hive Dashboard</a>
                    </p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Test notification system
     */
    public function testNotifications($testEmail = null, $testPhone = null)
    {
        $results = [];
        
        if ($testEmail) {
            $results['email'] = $this->sendEmail(
                $testEmail, 
                'Smart Hive Test Email', 
                'This is a test email from Smart Hive Solution. If you receive this, email notifications are working correctly!',
                'normal'
            );
        }
        
        if ($testPhone) {
            $results['sms'] = $this->sendSMS(
                $testPhone, 
                'Smart Hive Test SMS: Notification system is working correctly!'
            );
        }
        
        return $results;
    }
}
