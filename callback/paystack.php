<?php
/**
/ ********************************************************************* \
 *                                                                      *
 *   Paystack Payment Gateway                                           *
 *   Version: 1.0.0                                                     *
 *   Build Date: 15 May 2016                                            *
 *                                                                      *
 ************************************************************************
 *                                                                      *
 *   Email: support@paystack.com                                        *
 *   Website: https://www.paystack.com                                  *
 *                                                                      *
\ ********************************************************************* /
**/

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once dirname(__FILE__) . '/../paystack/functions.php';

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

// Retrieve data returned in payment gateway callback
$invoiceId = filter_input(INPUT_GET, "invoiceid");
$trxref = filter_input(INPUT_GET, "trxref");
$whmcsurl = $gatewayParams['whmcsurl'];
$sitename = $gatewayParams['sitename'];
$senderemail = $gatewayParams['senderemail'];

if ($gatewayParams['testMode'] == 'on') {
    $secretKey = $gatewayParams['testSecretKey'];
} else {
    $secretKey = $gatewayParams['liveSecretKey'];
}

if(strtolower(filter_input(INPUT_GET, 'go'))==='standard'){
    // falling back to standard
    $ch = curl_init();

    $isSSL = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443);
    
    $amountinkobo = filter_input(INPUT_GET, 'amountinkobo');
    $email = filter_input(INPUT_GET, 'email');
    $phone = filter_input(INPUT_GET, 'phone');
    $customername = filter_input(INPUT_GET, 'customername');
    if ($amountinkobo < 2500) {
        $fee = $amountinkobo*0.015;
    }else{        
        $fee = ($amountinkobo*0.015)+100;
    }
    $feeFormated = number_format($fee,2,'.',',');

    $callback_url = 'http' . ($isSSL ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] .
        $_SERVER['SCRIPT_NAME'] . '?invoiceid=' . rawurlencode($invoiceId);

    $txStatus = new stdClass();
    // set url
    curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/transaction/initialize/");

    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        array(
        'Authorization: Bearer '. trim($secretKey),
        'Content-Type: application/json'
        )
    );

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt(
        $ch,
        CURLOPT_POSTFIELDS,
        json_encode(
            array(
            "amount"=>$amountinkobo,
            "email"=>$email,
            "phone"=>$phone,
            "callback_url"=>$callback_url
            )
        )
    );
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSLVERSION, 6);

    // exec the cURL
    $response = curl_exec($ch);

    // should be 0
    if (curl_errno($ch)) {
        // curl ended with an error
        $txStatus->error = "cURL said:" . curl_error($ch);
        curl_close($ch);
    } else {
        //close connection
        curl_close($ch);

        // Then, after your curl_exec call:
        $body = json_decode($response);
        if (!$body->status) {
            // paystack has an error message for us
            $txStatus->error = "Paystack API said: " . $body->message;
        } else {
            // get body returned by Paystack API
            $txStatus = $body->data;
        }
    }
    if(!$txStatus->error){
        header('Location: ' . $txStatus->authorization_url);
        die('<meta http-equiv="refresh" content="0;url='.$txStatus->authorization_url.'" />
        Redirecting to <a href=\''.$txStatus->authorization_url.'\'>'.$txStatus->authorization_url.'</a>...');
    } else {
        if ($gatewayParams['gatewayLogs'] == 'on') {
            $output = "Transaction Initialize failed"
                . "\r\nReason: {$txStatus->error}";
            logTransaction($gatewayModuleName, $output, "Unsuccessful");
        }
        die($txStatus->error);
    }
}
/**
 * Verify Paystack transaction.
 */
$command = 'GetInvoice';
$postData = array(
    'invoiceid' => $invoiceid,
);
$adminuser = $whmcsuser; // Optional for WHMCS 7.2 and later


$invoiceresults = localAPI($command, $postData, $adminuser);

$txStatus = verifyTransaction($trxref, $secretKey);

if ($txStatus->error) {
    if ($gatewayParams['gatewayLogs'] == 'on') {
        $output = "Transaction ref: " . $trxref
            . "\r\nInvoice ID: " . $invoiceId
            . "\r\nStatus: failed"
            . "\r\nReason: {$txStatus->error}";
        logTransaction($gatewayModuleName, $output, "Unsuccessful");
    }
    $success = false;
} elseif ($txStatus->status == 'success') {
    if ($gatewayParams['gatewayLogs'] == 'on') {
        $output = "Transaction ref: " . $trxref
            . "\r\nInvoice ID: " . $invoiceId
            . "\r\nStatus: succeeded";                       
            $remoteTokenID = $txStatus->authorization->authorization_code;
            $userID = $invoiceresults['userid'];
            if (!empty($remoteTokenID)) {                
                /*update_query(
                    "tblclients",
                    array(
                        "cardnum" => "",
                        "gatewayid" => $remoteTokenID
                    ),
                    array("id" => $userID)
                );*/
                paystack_update_gatewayid( $userID, $remoteTokenID );
            }
        logTransaction($gatewayModuleName, $output, "Successful");
    }
    $success = true;
} else {
    if ($gatewayParams['gatewayLogs'] == 'on') {
        $output = "Transaction ref: " . $trxref
            . "\r\nInvoice ID: " . $invoiceId
            . "\r\nStatus: {$txStatus->status}";
        logTransaction($gatewayModuleName, $output, "Unsuccessful");
    }
    $success = false;
}

if ($success) {
    /**
     * Validate Callback Invoice ID.
     *
     * Checks invoice ID is a valid invoice number.
     *
     * Performs a die upon encountering an invalid Invoice ID.
     *
     * Returns a normalised invoice ID.
     */
    $invoiceId = checkCbInvoiceID($invoiceId, $gatewayModuleName);

    /**
     * Check Callback Transaction ID.
     *
     * Performs a check for any existing transactions with the same given
     * transaction number.
     *
     * Performs a die upon encountering a duplicate.
     */
    checkCbTransID($trxref);

    $amount = floatval($txStatus->amount)/100;
    if ($gatewayParams['convertto']) {
        $result = select_query("tblclients", "tblinvoices.invoicenum,tblclients.currency,tblcurrencies.code", array("tblinvoices.id" => $invoiceId), "", "", "", "tblinvoices ON tblinvoices.userid=tblclients.id INNER JOIN tblcurrencies ON tblcurrencies.id=tblclients.currency");
        $data = mysql_fetch_array($result);
        $invoice_currency_id = $data['currency'];

        $converto_amount = convertCurrency($amount, $gatewayParams['convertto'], $invoice_currency_id);
        $amount = format_as_currency($converto_amount);
        if ($amount < 2500) {
            $fee = $amount*0.015;
        }else{
            $fee = ($amount*0.015)+100; 
        }       
        $feeFormated = number_format($fee,2,'.',',');
    }

    /**
     * Add Invoice Payment.
     *
     * Applies a payment transaction entry to the given invoice ID.
     *
     * @param int $invoiceId         Invoice ID
     * @param string $transactionId  Transaction ID
     * @param float $paymentAmount   Amount paid (defaults to full balance)
     * @param float $paymentFee      Payment fee (optional)
     * @param string $gatewayModule  Gateway module name
     */
    addInvoicePayment($invoiceId, $trxref, $amount, $feeFormated, $gatewayModuleName);    

    // load invoice
    $isSSL = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443);
        
    $invoice_url = 'http' . ($isSSL ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] .
        substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], '/')) .
        '/../../../viewinvoice.php?id='.
        rawurlencode($invoiceId);

    header('Location: '.$invoice_url);
} else {
    $subject = "Failed Paystack Transaction Notification";
    $message = "<p>Dear $customername</p>
                <p>Unfortunately, the Paystack transaction was not successful. Find below the details of the transaction.</p>
                <p>Status: <strong>Unsuccessful Payment</strong></p>
                <p>Amount: <strong>N$amount</strong> (Fee: N$feeFormated)</p>                
                <p>Transaction Ref No: <strong>$trxref</strong></p>
                <p>Error Description: <strong>$txStatus->data->gateway_response</strong></p><br />
                <p>If you have any issues with this transaction, do not hesitate to send us a mail at {$senderemail} stating the transaction reference number.</p>

                --------------------------------
                <p>You can login to your client area at<br />
                <a href='$whmcsurl/viewinvoice.php?id={$invoiceid}'>$whmcsurl/viewinvoice.php?id={$invoiceid}</a></p>

                <p>$sitename</p>"; 

    $command = 'SendEmail';
    $postData = array(                  
        'id' => $invoiceresults['userid'],         
        'customtype' => 'general',
        'customsubject' => $subject,
        'custommessage' => $message,        
    );
    $adminuser = $whmcsuser; // Optional for WHMCS 7.2 and later

    $results = localAPI($command, $postData, $adminuser);
    die($txStatus->error . ' ; ' . $txStatus->status);
}

function verifyTransaction($trxref, $secretKey)
{
    $ch = curl_init();
    $txStatus = new stdClass();

    // set url
    curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/transaction/verify/" . rawurlencode($trxref));

    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        array(
        'Authorization: Bearer '. trim($secretKey)
        )
    );

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSLVERSION, 6);
    
    // exec the cURL
    $response = curl_exec($ch);
    
    // should be 0
    if (curl_errno($ch)) {
        // curl ended with an error
        $txStatus->error = "cURL said:" . curl_error($ch);
        curl_close($ch);
    } else {
        //close connection
        curl_close($ch);

        // Then, after your curl_exec call:
        $body = json_decode($response);
        if (!$body->status) {
            // paystack has an error message for us
            $txStatus->error = "Paystack API said: " . $body->message;
        } else {
            // get body returned by Paystack API
            $txStatus = $body->data;
        }
    }

    return $txStatus;
}
