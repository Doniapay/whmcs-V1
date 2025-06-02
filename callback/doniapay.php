<?php

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

use WHMCS\Config\Setting;

$invoiceId = $_REQUEST['invoice'];
$transactionId = $_REQUEST['transactionId'];
$paymentAmount = $_REQUEST['paymentAmount'];
$paymentFee = $_REQUEST['paymentFee'];
$gatewayModuleName = "doniapay";

$transaction_id_doniapay = $transactionId;

$data = array(
    "transaction_id" => $transaction_id_doniapay,
);

$apikey = $_GET['api'];
$secretkey = $_GET['secret'];
$hostname = $_GET['host'];

$header = array(
    "api" => $apikey,
    "url" => 'https://secure.doniapay.com/api/payment/verify',
);

$headers = array(
    'Content-Type: application/json',
    'donia-apikey: ' . $header['api'],
);


$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => $header['url'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_FOLLOWLOCATION => true,
));

$response = curl_exec($curl);
curl_close($curl);

$data = json_decode($response, true);

if ($data['status'] == "COMPLETED") {
   
    addInvoicePayment(
        $invoiceId,
        $transactionId,
        $paymentAmount,
        $paymentFee,
        $gatewayModuleName
    );

    
    $systemUrl = Setting::getValue('SystemURL');
    echo '<script>location.href = "' . $systemUrl . '/viewinvoice.php?id=' . $invoiceId . '";</script>';
} else {
    echo "Failed. ID Not Match or Payment Verification Failed.";
}
?>
