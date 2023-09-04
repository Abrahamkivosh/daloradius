<?php
// service to send SMS messages to users.

// Path: app/services/SMSService.php

class SMSService
{
    private $configValues;

    public function __construct()
    {
        // Load configuration
        include_once(__DIR__ . '/../../common/includes/config_read.php');

        $this->configValues = $configValues;
    }

    public function sendSMS($phone, $message)
    {
        $smsGateway = $this->configValues['CONFIG_SMS_GATEWAY'];

        switch($smsGateway) {
            case 'clickatell':
                $this->sendSMSClickatell($phone, $message);
                break;
            case 'twilio':
                $this->sendSMSTwilio($phone, $message);
                break;
            case 'africastalking':
                $this->sendSMSAfricastalking($phone, $message);
                break;
            default:
                die("Unknown SMS gateway: $smsGateway");
        }
    }

    private function sendSMSClickatell($phone, $message)
    {
        $clickatellUser = $this->configValues['CONFIG_CLICKATELL_USER'];
        $clickatellPassword = $this->configValues['CONFIG_CLICKATELL_PASSWORD'];
        $clickatellApiId = $this->configValues['CONFIG_CLICKATELL_API_ID'];

        $url = "http://api.clickatell.com/http/sendmsg?user=$clickatellUser&password=$clickatellPassword&api_id=$clickatellApiId&to=$phone&text=$message";

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);

        curl_close($ch);

        if($result == false) {
            die("Error sending SMS: " . curl_error($ch));
        }
    }

    private function sendSMSTwilio($phone, $message)
    {
        $twilioAccountSid = $this->configValues['CONFIG_TWILIO_ACCOUNT_SID'];
        $twilioAuthToken = $this->configValues['CONFIG_TWILIO_AUTH_TOKEN'];
        $twilioFromNumber = $this->configValues['CONFIG_TWILIO_FROM_NUMBER'];

        $url = "https://api.twilio.com/2010-04-01/Accounts/$twilioAccountSid/SMS/Messages";

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_USERPWD, "$twilioAccountSid:$twilioAuthToken");

        curl_setopt($ch, CURLOPT_POST, true);
            
            curl_setopt($ch, CURLOPT_POSTFIELDS, array(
                'From' => $twilioFromNumber,
                'To' => $phone,
                'Body' => $message
            ));

        $result = curl_exec($ch);

        curl_close($ch);

        if($result == false) {
            die("Error sending SMS: " . curl_error($ch));
        }
    }
    private function sendSMSAfricastalking($phone, $message)
    {
        // Set your app credentials
        $username   = $this->configValues['CONFIG_AFRICASTALKING_USERNAME'];
        $apikey     = $this->configValues['CONFIG_AFRICASTALKING_API_KEY'];

        // Initialize the SDK
        $AT         = new AfricasTalking($username, $apikey);

        // Get the SMS service
        $sms        = $AT->sms();

        // Set the numbers you want to send to in international format
        $recipients = $phone;

        // Set your message
        $message    = $message;

        // Set your shortCode or senderId
        $from       = $this->configValues['CONFIG_AFRICASTALKING_SHORTCODE'];

        try {
            // Thats it, hit send and we'll take care of the rest
            $result = $sms->send([
                'to'      => $recipients,
                'message' => $message,
                'from'    => $from
            ]);

            print_r($result);
        } catch (Exception $e) {
            echo "Error: ".$e->getMessage();
        }

    }

    public function sendSMSMessage($phone, $message)
    {
        $this->sendSMS($phone, $message);
    }

}