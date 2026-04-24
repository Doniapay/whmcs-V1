<?php

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

use WHMCS\Config\Setting;

$gatewayModuleName = "doniapay";
$gatewayParams = getGatewayVariables($gatewayModuleName);

if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

// Doniapay typically returns 'ids' as transaction id and 'invoice' via GET/POST
$invoiceId = $_REQUEST['invoice'];
$transactionId = $_REQUEST['ids'] ?: $_REQUEST['transactionId'];

if (!$transactionId || !$invoiceId) {
    die("Invalid Request");
}

$apiKey = $gatewayParams['apiKey'];
$apiUrl = 'https://api.doniapay.com/v2/order/synchronize/confirm';

$postData = array(
    "transaction_id" => $transactionId,
);

$headers = array(
    'Content-Type: application/json',
    'X-Signature-Key: ' . $apiKey,
);

$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($postData),
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30,
));

$response = curl_exec($curl);
$curlError = curl_error($curl);
curl_close($curl);

if ($curlError) {
    die("CURL Error: " . $curlError);
}

$data = json_decode($response, true);

// New API Success Status is 'Paid'
if (isset($data['status']) && $data['status'] == "Paid") {
    
    // Check if transaction already exists in WHMCS
    checkCbTransID($transactionId);
    
    // Add payment to invoice
    // Note: $data['amount'] returns the actual paid amount from gateway
    addInvoicePayment(
        $invoiceId,
        $transactionId,
        $data['amount'], // Real amount from API
        0,               // Payment Fee
        $gatewayModuleName
    );

    logTransaction($gatewayParams['name'], $response, "Successful");

    $systemUrl = Setting::getValue('SystemURL');
    header("Location: " . $systemUrl . "viewinvoice.php?id=" . $invoiceId);
    exit;
} else {
    logTransaction($gatewayParams['name'], $response, "Unsuccessful");
    echo "Payment verification failed. Status: " . ($data['status'] ?? 'Unknown');
}
