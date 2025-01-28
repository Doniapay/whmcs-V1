<?php
    /* doniapay WHMCS Gateway
     *
     * Copyright (c) 2024 doniapay
     * Website: https://doniapay.com
     * Developer: doniapay LTD
     */
    
    require_once __DIR__ . '/../../../init.php';
    require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
    require_once __DIR__ . '/../../../includes/invoicefunctions.php';
    
    use WHMCS\Config\Setting;


    $invoiceId = $_GET['invoice'];
    $transactionId = $_GET['transactionId'];
    $paymentAmount = $_GET['paymentAmount'];
    $paymentFee = $_GET['paymentFee'];
    $gatewayModuleName = "doniapay";


    $transaction_id_doniapay = $transactionId;

    $data   = array(
        "transaction_id"          => $transaction_id_doniapay,
    );
    $apikey = $_GET['api'];
    $secretkey = $_GET['secret'];
    $hostname = $_GET['host'];

    $header   = array(
        "api"               => $apikey,
        "secret"            => $secretkey,
        "position"          => $hostname,
        "url"               => 'https://pay.doniapay.com/request/payment/verify',
    );


    $headers = array(
        'Content-Type: application/x-www-form-urlencoded',
        'app-key: ' . $header['api'],
        'secret-key: ' . $header['secret'],
        'host-name: ' . $header['position'],
    );
    $url = $header['url'];
    $curl = curl_init();
    $data = http_build_query($data);
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_VERBOSE =>true
    ));
     
    $response = curl_exec($curl);
    curl_close($curl);
    $data = json_decode($response,true);
    
    if($data['status'] == 1){
        addInvoicePayment(
            $invoiceId,
            $transactionId,
            $paymentAmount,
            $paymentFee,
            $gatewayModuleName
        );
        
        $systemUrl = Setting::getValue('SystemURL');
?>
        <script>
            location.href="<?php echo $systemUrl . '/viewinvoice.php?id=' . $invoiceId;?>";
        </script>
<?php
    }else{
        echo "Failed. Id Not Match";
    }
?>