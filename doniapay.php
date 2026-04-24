<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function doniapay_MetaData()
{
    return array(
        'DisplayName' => 'Doniapay',
        'APIVersion' => '2.0',
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => false,
    );
}

function doniapay_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Doniapay',
        ),
        'apiKey' => array(
            'FriendlyName' => 'API Key',
            'Type' => 'text',
            'Size' => '150',
            'Default' => '',
            'Description' => 'Enter Your Doniapay Api Key',
        ),
        'currency_rate' => array(
            'FriendlyName' => 'USD Conversion Rate',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '115',
            'Description' => '1 USD = How much BDT? (e.g. 115)',
        )
    );
}

function doniapay_link($params)
{
    // Invoice view logic
    if (isset($_POST['pay_doniapay'])) {
        $response = doniapay_payment_url($params);
        if ($response->status) {
            header("Location: " . $response->payment_url);
            exit;
        }
        return '<div class="alert alert-danger">' . $response->message . '</div>';
    }

    return '<form action="" method="POST">
                <input type="hidden" name="pay_doniapay" value="true" />
                <input class="btn btn-primary" type="submit" value="' . $params['langpaynow'] . '" />
            </form>';
}

function doniapay_payment_url($params)
{
    $apiKey = $params['apiKey'];
    $currencyRate = $params['currency_rate'];
    $invoiceId = $params['invoiceid'];
    $amount = $params['amount'];

    // Currency Conversion
    if ($params['currency'] == "USD") {
        $amount = $amount * $currencyRate;
    }

    $systemUrl = $params['systemurl'];
    $successUrl = $params['returnurl'];
    $cancelUrl = $params['returnurl'];
    $webhookUrl = $systemUrl . 'modules/gateways/callback/doniapay.php';

    $cusName = $params['clientdetails']['firstname'] . " " . $params['clientdetails']['lastname'];
    $cusEmail = $params['clientdetails']['email'];

    // New API Raw Data Format
    $rawData = array(
        "dn_su"  => $successUrl,
        "dn_cu"  => $cancelUrl,
        "dn_wu"  => $webhookUrl,
        "dn_am"  => round($amount, 2),
        "dn_cn"  => $cusName,
        "dn_ce"  => $cusEmail,
        "dn_mt"  => json_encode(array("invoice_id" => $invoiceId)),
        "dn_rt"  => "GET"
    );

    // Payload & Signature Generation
    $payload = base64_encode(json_encode($rawData));
    $signature = hash_hmac('sha256', $payload, $apiKey);

    $apiEndpoint = 'https://api.doniapay.com/v2/order/synchronize/prepare';

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $apiEndpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(array('dp_payload' => $payload)),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'X-Signature-Key: ' . $apiKey,
            'donia-signature: ' . $signature
        ),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ));

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return (object)[
            'status' => false,
            'message' => 'Connection Error: ' . $curlError
        ];
    }

    $res = json_decode($response, true);

    if (isset($res['status']) && $res['status'] == 'success') {
        return (object)[
            'status' => true,
            'payment_url' => $res['payment_url']
        ];
    } else {
        return (object)[
            'status' => false,
            'message' => $res['message'] ?? 'Gateway Error: Unable to initiate payment.'
        ];
    }
}
