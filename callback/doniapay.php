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

$apikey = $gatewayParams['apiKey'];
$systemUrl = $gatewayParams['systemurl'];

$invoiceId = isset($_REQUEST['invoice']) ? $_REQUEST['invoice'] : '';
$transactionId = isset($_REQUEST['transactionId']) ? $_REQUEST['transactionId'] : (isset($_REQUEST['payment_id']) ? $_REQUEST['payment_id'] : '');

if (empty($invoiceId) || empty($transactionId)) {
    $rawPost = json_decode(file_get_contents('php://input'), true);
    if ($rawPost) {
        $invoiceId = $invoiceId ?: ($rawPost['invoice'] ?? '');
        $transactionId = $transactionId ?: ($rawPost['transaction_id'] ?? $rawPost['transactionId'] ?? '');
    }
}

if (empty($invoiceId) || empty($transactionId)) {
    logTransaction($gatewayParams['name'], $_REQUEST, "Unsuccessful - Missing Required Parameters");
    die("Invalid request");
}

$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);
checkCbTransID($transactionId); 

$api_url = "https://api.doniapay.com/v2/order/synchronize/confirm";

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["transaction_id" => $transactionId]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "X-Signature-Key: " . $apikey,
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
curl_close($ch);

$res = json_decode($response, true);

if ($res && ($res['status'] == 'COMPLETED' || $res['status'] == 1)) {
    
    addInvoicePayment(
        $invoiceId,
        $transactionId,
        "",
        "0.00", 
        $gatewayModuleName
    );

    logTransaction($gatewayParams['name'], array_merge($_REQUEST, (array)$res), "Successful");

    header("Location: " . $systemUrl . "/viewinvoice.php?id=" . $invoiceId . "&paymentsuccess=true");
    exit;

} else {
    
    logTransaction($gatewayParams['name'], array_merge($_REQUEST, (array)$res), "Unsuccessful - Verification Failed");

    header("Location: " . $systemUrl . "/viewinvoice.php?id=" . $invoiceId . "&paymentfailed=true");
    exit;
}

?>
