<?php

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

require_once dirname(__FILE__) . '/functions.php';

$invoice = $_GET['invoice'];
$name = $_GET['name'];
$amount = $_GET['amount'];
$email = $_GET['customerEmail'];


$gatewayParams = getGatewayVariables('paystack');

if ($gatewayParams['testMode'] == 'on') {
    $secretKey = $gatewayParams['testSecretKey'];
} else {
    $secretKey = $gatewayParams['liveSecretKey'];
}

$result = getInvoiceDetails($invoice, $gatewayParams['whmcsuser']);
$invoiceAmount = $result['balance'];

if (($invoiceAmount==0) || ($invoiceAmount=='0.00')){
	die('This invoice has already been marked as paid.');
}

if ($invoiceAmount == $amount){
	$fullAmount = $amount;
}else{
	die('Invoice Amount does not equal amount paid');
}

$actualAmountFormated = number_format($fullAmount,2,'.',',');

$mcheck = md5($invoice.$email.$amount);
$mcheck = strtoupper($mcheck);

if($mcheck != $_GET['mcheck']){
	die('Access Denied');
}

$result = array();
// Pass the customer's authorisation code, email and amount
$postdata =  array( 'authorization_code' => $_GET['authcode'],'email' => $email, 'amount' => $fullAmount*100);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL,"https://api.paystack.co/transaction/charge_authorization");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($postdata));  //Post Fields
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$headers = [
  'Authorization: Bearer '. trim($secretKey),
  'Content-Type: application/json',
];

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$request = curl_exec ($ch);

curl_close ($ch);
if ($request) {
  $result = json_decode($request, true);
}

$reference = $result['data']['reference'];
$message = $result['data']['gateway_response'];

if ($fullAmount < 2500) {
	$fee = $fullAmount*0.015;
}else{
	$fee = ($fullAmount*0.015)+100;	
}
$feeFormated = number_format($fee,2,'.',',');

if($result['data']['status'] == 'success')
{	
	$amount = $fullAmount;

	$output = "Transaction ref: " . $reference
            . "\r\nInvoice ID: " . $invoice
            . "\r\nStatus: succeeded";  
	logtransaction ('Paystack', $output , "Successful payment of invoice #{$invoice}");
        
	addinvoicepayment ($invoice, $reference, $fullAmount*100, $fee, 'paystack');
	 
	$url = $_GET['whmcs'].'/host/viewinvoice.php?id='.$invoice;	
	
	header("location: ".$url);     
}else{
	$url = $_GET['whmcs'].'/host/viewinvoice.php?id='.$invoice;	  	
}	  
 

?>