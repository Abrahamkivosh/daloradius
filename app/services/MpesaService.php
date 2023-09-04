<?php

class MpesaService
{
    private $configValues;

    public function __construct()
    {
        // Load configuration
        include_once __DIR__ . '/../../common/includes/config_read.php';

        $this->configValues = $configValues;
    }

    /**
     * Get access token from Safaricom API
     * @return mixed
     */
    private function getAccessToken()
    {
        $consumerKey = $this->configValues['CONFIG_MPESA_CONSUMER_KEY'];
        $consumerSecret = $this->configValues['CONFIG_MPESA_CONSUMER_SECRET'];
        $credentials = base64_encode($consumerKey . ':' . $consumerSecret);

        $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);

        curl_close($ch);

        if ($result == false) {
            die("Error getting access token: " . curl_error($ch));
        }

        $json = json_decode($result);

        return $json->access_token;
    }

    /**
     * Register confirmation and validation URLs
     * @return mixed
     */
    public function registerURLs()
    {
        $url = $this->configValues['CONFIG_MPESA_REGISTER_URL'];
        $accessToken = $this->getAccessToken();
        $payload = [
            'ShortCode' => $this->configValues['CONFIG_MPESA_SHORTCODE'],
            'ResponseType' => 'Completed',
            'ConfirmationURL' => $this->configValues['CONFIG_MPESA_CONFIRMATION_URL'],
            'ValidationURL' => $this->configValues['CONFIG_MPESA_VALIDATION_URL'],
        ];

        $payload = json_encode($payload);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization:Bearer ' . $accessToken));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        if ($result == false) {
            die("Error registering URLs: " . curl_error($ch));
        }

        $json = json_decode($result);

        return $json;

    }

    /**
     * validate the transaction before processing
     * Accept the Transaction or Reject it
     * @param $status
     */
    public function validateTransaction(bool $status = true)
    {
        $result = [
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted',
        ];

        if (!$status) {
            $result['ResultCode'] = C2B00012; // Invalid Acc No
            $result['ResultDesc'] = 'Rejected';
        }

        header('Content-Type: application/json');
        echo json_encode($result);
    }

    /**
     * Get the transaction details from the callback
     */
    public function getTransactionDetails()
    {
        $callbackJSONData = file_get_contents('php://input');
        $callbackData = json_decode($callbackJSONData);

        $transactionType = $callbackData->TransactionType;
        $transID = $callbackData->TransID; // This is unique to every transaction
        $transTime = $callbackData->TransTime; // Format is YYYYMMDDHHMMSS
        $transAmount = $callbackData->TransAmount; // This is the amount that was transacted
        $businessShortCode = $callbackData->BusinessShortCode; // This is the paybill number
        $billRefNumber = $callbackData->BillRefNumber; // This is the account number
        $invoiceNumber = $callbackData->InvoiceNumber; // This is the invoice number
        $orgAccountBalance = $callbackData->OrgAccountBalance; // This is the organization's account balance
        $thirdPartyTransID = $callbackData->ThirdPartyTransID; // This is the transaction ID from the third-party
        // $MSISDN = $callbackData->MSISDN; // This is a masked mobile number of the customer making the payment.
        $firstName = $callbackData->FirstName; // This is the first name of the customer making the payment.
        $middleName = $callbackData->MiddleName; // This is the middle name of the customer making the payment.
        $lastName = $callbackData->LastName; // This is the last name of the customer making the payment.

        return $callbackData;

    }

    /**
     * Lipa Na Mpesa Online
     * @param $amount, $phone, $account, $description
     * @return mixed
     */
    public function lipaNaMpesaOnline($amount, $phone, $account, $description = 'Lipa Net')
    {
        $url = $this->configValues['CONFIG_MPESA_LNM_URL'];
        $accessToken = $this->getAccessToken();
        $timestamp = date('YmdHis');
        $password = base64_encode($this->configValues['CONFIG_MPESA_SHORTCODE'] . $this->configValues['CONFIG_MPESA_PASSKEY'] . $timestamp);

        $payload = [
            'BusinessShortCode' => $this->configValues['CONFIG_MPESA_SHORTCODE'],
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => $this->configValues['CONFIG_MPESA_SHORTCODE'],
            'PhoneNumber' => $phone,
            'CallBackURL' => $this->configValues['CONFIG_MPESA_LNM_CALLBACK_URL'],
            'AccountReference' => $account,
            'TransactionDesc' => $description,
        ];

        $payload = json_encode($payload);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization:Bearer ' . $accessToken));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        if ($result == false) {
            die("Error processing payment: " . curl_error($ch));
        }

        $json = json_decode($result);

        return $json;
    }

    /**
     * Call back function for Lipa Na Mpesa Online
     * @return mixed
     */
    public function lipaNaMpesaOnlineCallback()
    {
        $callbackJSONData = file_get_contents('php://input');
        $callbackData = json_decode($callbackJSONData);

        $MerchantRequestID = $callbackData->Body->stkCallback->MerchantRequestID;
        $CheckoutRequestID = $callbackData->Body->stkCallback->CheckoutRequestID;
        $ResultCode = $callbackData->Body->stkCallback->ResultCode;

        if ($ResultCode == 0) {
            $ResultDesc = $callbackData->Body->stkCallback->ResultDesc;
            $Amount = $callbackData->Body->stkCallback->CallbackMetadata->Item[0]->Value;
            $MpesaReceiptNumber = $callbackData->Body->stkCallback->CallbackMetadata->Item[1]->Value;
            $Balance = $callbackData->Body->stkCallback->CallbackMetadata->Item[2]->Value;
            $TransactionDate = $callbackData->Body->stkCallback->CallbackMetadata->Item[3]->Value;
            $PhoneNumber = $callbackData->Body->stkCallback->CallbackMetadata->Item[4]->Value;

            $result = [
                'ResultCode' => $ResultCode,
                'ResultDesc' => $ResultDesc,
                'MerchantRequestID' => $MerchantRequestID,
                'CheckoutRequestID' => $CheckoutRequestID,
                'Amount' => $Amount,
                'MpesaReceiptNumber' => $MpesaReceiptNumber,
                'Balance' => $Balance,
                'TransactionDate' => $TransactionDate,
                'PhoneNumber' => $PhoneNumber,
            ];

        } else {
            $details = $callbackData->Body->stkCallback;

            $result = [
                'ResultCode' => $details->ResultCode,
                'ResultDesc' => $details->ResultDesc,
                'MerchantRequestID' => $details->MerchantRequestID,
                'CheckoutRequestID' => $details->CheckoutRequestID,
            ];

        }

        if ($result['ResultCode'] == 0) {
            // Payment was successful
            return [
                'status' => true,
                'data' => $result];
        } else {
            // Payment failed
            return [
                'status' => false,
                'data' => $result];
        }

    }

}
