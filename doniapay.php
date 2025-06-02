<?php


if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function doniapay_MetaData()
{
    return array(
        'DisplayName' => 'doniapay',
        'APIVersion' => '1.0',
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => false,
    );
}




function doniapay_link($params)
{
    $host_config = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $host_config = pathinfo($host_config, PATHINFO_FILENAME);

    if (isset($_POST['pay'])) {
        $response = doniapay_payment_url($params);
        if ($response->status) {
            return '<form action="' . $response->payment_url . ' " method="GET">
            <input class="btn btn-primary" type="submit" value="' . $params['langpaynow'] . '" />
            </form>';
        }

        return $response->message;
    }


    if ($host_config == "viewinvoice") {
        return '<form action="" method="POST">
        <input class="btn btn-primary" name="pay" type="submit" value="' . $params['langpaynow'] . '" />
        </form>';
    } else {
        $response = doniapay_payment_url($params);
        if ($response->status) {
            return '<form action="' . $response->payment_url . ' " method="GET">
            <input class="btn btn-primary" type="submit" value="' . $params['langpaynow'] . '" />
            </form>';
        }

        return $response->message;
    }
}


function doniapay_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'doniapay',
        ),
        'apiKey' => array(
            'FriendlyName' => 'API Key',
            'Type' => 'text',
            'Size' => '150',
            'Default' => '',
            'Description' => 'Enter Your Api Key',
        ),

        'currency_rate' => array(
            'FriendlyName' => 'Currency Rate',
            'Type' => 'text',
            'Size' => '150',
            'Default' => '85',
            'Description' => 'Enter Dollar Rate',
        )
    );
}

function doniapay_payment_url($params)
{
    $cus_name = $params['clientdetails']['firstname'] . " " . $params['clientdetails']['lastname'];
    $cus_email = $params['clientdetails']['email'];

    $apikey = $params['apiKey'];
    $currency_rate = $params['currency_rate'];
    $invoiceId = $params['invoiceid'];

    if ($params['currency'] == "USD") {
        $amount = $params['amount'] * $currency_rate;
    } else {
        $amount = $params['amount'];
    }

    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
        $url = "https://";
    else
        $url = "http://";
    $url .= $_SERVER['HTTP_HOST'];
    $systemUrl = $url;

    $webhook_url = $systemUrl . '/modules/gateways/callback/doniapay.php?api=' . $apikey . '&invoice=' . $invoiceId;
    $success_url = $systemUrl . '/viewinvoice.php?id=' . $invoiceId;
    $cancel_url = $systemUrl . '/viewinvoice.php?id=' . $invoiceId;

    $data = array(
        "cus_name"      => $cus_name,
        "cus_email"     => $cus_email,
        "amount"        => $amount,
        "webhook_url"   => $webhook_url,
        "success_url"   => $success_url,
        "cancel_url"    => $cancel_url,
    );

    $headers = array(
        'Content-Type: application/json',
        'donia-apikey: ' . $apikey,
    );

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => 'https://secure.doniapay.com/api/payment/create',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => $headers,
    ));

    $response = curl_exec($ch);
    curl_close($ch);

    $res = json_decode($response, true);


    if (!empty($res['status']) && !empty($res['payment_url'])) {
        return (object)[
            'status' => true,
            'payment_url' => $res['payment_url']
        ];
    } else {
        return (object)[
            'status' => false,
            'message' => $res['message'] ?? 'Unable to generate payment URL.'
        ];
    }
}

