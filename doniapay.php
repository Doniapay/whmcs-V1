<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function doniapay_MetaData()
{
    return array(
        'DisplayName' => 'DoniaPay',
        'APIVersion' => '1.1', // Updated for V2 API
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => false,
    );
}

function doniapay_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'DoniaPay V2',
        ),
        'apiKey' => array(
            'FriendlyName' => 'API Key',
            'Type' => 'text',
            'Size' => '150',
            'Default' => '',
            'Description' => 'Enter Your DoniaPay API Key',
        ),
        'currency_rate' => array(
            'FriendlyName' => 'Currency Rate',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '1',
            'Description' => 'Exchange rate if store currency is not BDT (e.g., 120 for USD to BDT)',
        )
    );
}

function doniapay_link($params)
{
    $host_config = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $host_config = pathinfo($host_config, PATHINFO_FILENAME);

    if (isset($_POST['pay']) || $host_config != "viewinvoice") {
        $response = doniapay_payment_url($params);
        if ($response->status) {
            return '<form action="' . $response->payment_url . '" method="GET">
            <input class="btn btn-primary" type="submit" value="' . $params['langpaynow'] . '" />
            </form>';
        }
        return '<div class="alert alert-danger">' . $response->message . '</div>';
    }

    if ($host_config == "viewinvoice") {
        return '<form action="" method="POST">
        <input class="btn btn-primary" name="pay" type="submit" value="' . $params['langpaynow'] . '" />
        </form>';
    }
}

function doniapay_payment_url($params)
{
    $apikey = trim($params['apiKey']);
    $currency_rate = (float)$params['currency_rate'];
    $invoiceId = $params['invoiceid'];

    // Customer Info
    $cus_name = trim($params['clientdetails']['firstname'] . " " . $params['clientdetails']['lastname']);
    $cus_email = trim($params['clientdetails']['email']);
    $cus_phone = isset($params['clientdetails']['phonenumber']) ? $params['clientdetails']['phonenumber'] : '00000000000';

    if (strtoupper($params['currency']) != "BDT" && $currency_rate > 0) {
        $amount = (float)$params['amount'] * $currency_rate;
    } else {
        $amount = (float)$params['amount'];
    }
    
    $final_amount = $amount * 1.02;

    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
    $systemUrl = $protocol . $_SERVER['HTTP_HOST'];
    
    $webhook_url = $systemUrl . '/modules/gateways/callback/doniapay.php?invoice=' . $invoiceId;
    $success_url = $systemUrl . '/viewinvoice.php?id=' . $invoiceId . '&paymentsuccess=true';
    $cancel_url  = $systemUrl . '/viewinvoice.php?id=' . $invoiceId . '&paymentfailed=true';


    $api_url = "https://api.doniapay.com/v2/order/synchronize/prepare";

    $raw_data = [
        "dn_su"  => $success_url, 
        "dn_cu"  => $cancel_url,
        "dn_wu"  => $webhook_url, 
        "dn_am"  => (string)round($final_amount, 2), 
        "dn_cn"  => $cus_name,          
        "dn_ce"  => $cus_email,
        "dn_mt"  => json_encode(["phone" => $cus_phone, "invoice_id" => $invoiceId]), 
        "dn_rt"  => "GET"
    ];

    $payload   = base64_encode(json_encode($raw_data));
    $signature = hash_hmac('sha256', $payload, $apikey);

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['dp_payload' => $payload]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-Signature-Key: $apikey",
        "donia-signature: $signature",
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return (object)['status' => false, 'message' => 'cURL Error: ' . $err];
    }

    $res = json_decode($response, true);

    if (isset($res['status']) && $res['status'] == 1 && !empty($res['payment_url'])) {
        return (object)[
            'status' => true,
            'payment_url' => $res['payment_url']
        ];
    } else {
        $error_msg = isset($res['message']) ? $res['message'] : 'Unknown error from gateway.';
        return (object)[
            'status' => false,
            'message' => 'DoniaPay Error: ' . $error_msg
        ];
    }
}
?>
