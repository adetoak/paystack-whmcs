<?php

use Illuminate\Database\Capsule\Manager as Capsule;
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

$gatewayParams = getGatewayVariables('paystack');

function paystack_update_gatewayid( $userid, $gatewayid ){ 
//try to create table

try
{
  Capsule::schema()->create('paystackpy',
			function ($table) {
				/** @var \Illuminate\Database\Schema\Blueprint $table */
				$table->increments('id');
				$table->string('custid');
				$table->string('gatewayid');
			}
		  );
		  
	 Capsule::table('paystackpy')
			->insert(
				[
				    'custid' => $userid,
					'gatewayid' => $gatewayid,
				]
			);
		Capsule::table('tblclients')
			->where('id', $userid)
			->update(
				[
					'gatewayid' => $gatewayid,
				]
			);	 
}

catch(Exception $e)
{
  $existing = paystack_get_gatewayid( $userid );
  
  if( !empty( $existing ))
  {
        Capsule::table('paystackpy')
			->where('custid', $userid)
			->update(
				[
					'gatewayid' => $gatewayid,
				]
			);	 
  }
  else
  {
     	   Capsule::table('paystackpy')
			->insert(
				[
				    'custid' => $userid,
					'gatewayid' => $gatewayid,
				]
			);
  }  
  Capsule::table('tblclients')
			->where('id', $userid)
			->update(
				[
					'gatewayid' => $gatewayid,
				]
			);	 
}	

catch(Exception $e)
	{
	  		return false;
	}
}

function paystack_get_gatewayid( $userid ){ 
	if( empty( $userid ) )
		return false;
	
	try {		
	
		$gatewayid = Capsule::table('paystackpy')
		->where('custid', $userid)->pluck('gatewayid');;

			/*$gatewayid = Capsule::table('tblclients')
			->where('id', $userid)->pluck('gatewayid');;	*/			

		return $gatewayid;
		
	} catch (\Exception $e) {
		return false;
	}

}

function getInvoiceDetails($invoiceid, $whmcsuser){
	$command = 'getinvoice';
	$values = array( 'invoiceid' => $invoiceid);
	$adminuser = $whmcsuser;
	 
	// Call API
	$results = localAPI($command, $values, $adminuser);
	//if ($results['result']!="success") echo "An Error Occurred: ".$results['result'];
	
	return $results;
}

function paystack_delete_gatewayid($userid){
	$return = true;
	//$gatewayid = '';
	if (empty($userid))
		$return = false;

	try{
		Capsule::table('paystackpy')
			->where('custid', $userid)
			->delete();	
			$return = true;
  	} catch (\Exception $e){
  		$return = false;
  	}

  	$command = "logactivity";
	$adminuser = "apiwgh";
	$values["description"] = "UserID: {$userid} has unsubscribed from Paystack";
	$values['userid'] = $userid;

	$results = localAPI($command,$values,$adminuser);

  	return $return;
	
}

function deletesuccess($name,$email){
	global $sender_email, $sender_name, $receiver_email;

	$content =  "
<!Doctype html>
<html lang='en'>
	<head>
		<style>
		html, body{background: #EEE; margin: 0}
		.container{background: #FFF; max-width: 400px; margin: 0 auto; border: 1px solid #CCC; font-family: Arial; font-size: 12px; padding: 30px;}
		#logo{margin-bottom: 20px;}
		</style>
	</head>
	<body>
		<div class='container'>
			<div id='logo'><img src='https://www.whogohost.com/assets/images/whlogo.png' height=50 /></div>
			<div id='body'>
				<p>Dear ".$name."</p>
				<p>Your have successfully unsubscribed from Paystack gateway on Whogohost. You can always subscribe again by paying for your next invoice using Paystack.</p>
				<p>Thank you for choosing Whogohost.</strong></p>
			</div>
		</div>
	</body>
</html>";
	if (!isset($sender_name)){
		$sender_name = "Whogohost Billing";
	}
	if (!isset($sender_email)){
		$sender_email = "support@whogohost.com";
	}
	if (!isset($receiver_email)){
		$receiver_email = "tobaniyi@whogohost.com";
	}
	$headers  = 'MIME-Version: 1.0' . "\r\n";
	$headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
    $headers .= 'From: '.$sender_name.'<'.$sender_email.'>' . "\r\n";
	
	$subject = "You have unsubscribed from Paystack";
	// send email
	mail($email,$subject,$content,$headers);

}


function mailsuccess($name,$amount,$feeFormated,$invid,$reference,$email)
{
	global $sender_email, $sender_name, $receiver_email;
$clientemail = $email;

  $content =  "
<!Doctype html>
<html lang='en'>
	<head>
		<style>
		html, body{background: #EEE; margin: 0}
		.container{background: #FFF; max-width: 400px; margin: 0 auto; border: 1px solid #CCC; font-family: Arial; font-size: 12px; padding: 30px;}
		#logo{margin-bottom: 20px;}
		</style>
		<script type='text/javascript'>
		function inIframe () {
		    if ( window.location !== window.parent.location ) {
		    	javascript:window.parent.location.reload();
		    }else{
		    	window.location='/host/viewinvoice.php?id={$invid}';
		    }
		}
		</script>
	</head>
	<body>
		<div class='container'>
			<div id='logo'><img src='https://www.whogohost.com/assets/images/whlogo.png' height=50 /></div>
			<div id='body'>
				<p>Dear ".$name."</p>
				<p>Your Paystack transaction was successful. View details below</p>
				<p>Status: <strong>Approved</strong></p>
				<p>Payment Reference: <strong>".$reference."</strong></p>
				<p>Amount: <strong>".$amount." (Fee:".$feeFormated.")</strong></p>
				<p>Invoice id: <strong>".$invid."</strong></p><br />
				<p>If you have any issues with this transaction, do not hesitate to send us a mail at billing@whogohost.com stating the transaction reference number.</p>
				<p>--------------------------------</p>
				<p>You can login to your client area at <a href='https://www.whogohost.com/host/viewinvoice.php?id=$invid'>https://www.whogohost.com/host/viewinvoice.php?id=$invid</a></p>
				<p>Whogohost</p>
			</div>
		</div>
		

	</body>
</html>";

$sendercontent =  "
<!Doctype html>
<html lang='en'>
	<head>
		<style>
		html, body{background: #EEE; margin: 0}
		.container{background: #FFF; max-width: 400px; margin: 0 auto; border: 1px solid #CCC; font-family: Arial; font-size: 12px; padding: 30px;}
		#logo{margin-bottom: 20px;}
		</style>
		
	</head>
	<body>
		<div class='container'>
			<div id='logo'><img src='https://www.whogohost.com/assets/images/whlogo.png' height=50 /></div>
			<div id='body'>
				<p>".$name." just paid for invoice no ".$invid." using Paystack</p>
				<p>Status: <strong>Approved</strong></p>
				<p>Payment Reference: <strong>".$reference."<strong></p>
				<p>Amount: <strong>".$amount." (Fee:".$feeFormated.")</strong></p>
				<p>Invoice id: <strong>".$invid."</strong></p>
				<p>--------------------------------</p>
				<p><a href='https://www.whogohost.com/host/admin/invoices.php?action=edit&id={$invid}'>https://www.whogohost.com/host/admin/invoices.php?action=edit&id={$invid}</a></p>
			</div>
		</div>
		

	</body>
</html>";


            $headers  = 'MIME-Version: 1.0' . "\r\n";
			$headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
			if (!isset($sender_name)){
				$sender_name = "Whogohost Billing";
			}
			if (!isset($sender_email)){
				$sender_email = "support@whogohost.com";
			}
			if (!isset($receiver_email)){
				$receiver_email = "tobaniyi@whogohost.com";
			}
            $headers .= 'From: '.$sender_name.'<'.$sender_email.'>' . "\r\n";
			
			$subject = 'Successful Paystack Payment of NGN'.$amount.' (Invoice #'.$invid.')';
			$receiver_subject = "Successful Paystack Payment";
			// send email
			mail($email,$subject,$content,$headers);
            mail($receiver_email,$receiver_subject,$sendercontent,$headers);
}

function mailfailure($name,$amount,$invid,$reference,$message,$email)
{
	global $sender_email, $sender_name, $receiver_email;
//$senderemail =  $gatewayParams['sender_email'];
$clientemail = $email;

  $content =  "
<!Doctype html>
<html lang='en'>
	<head>
		<style>
		html, body{background: #EEE; margin: 0}
		.container{background: #FFF; max-width: 400px; margin: 0 auto; border: 1px solid #CCC; font-family: Arial; font-size: 12px; padding: 30px;}
		#logo{margin-bottom: 20px;}
		</style>
		
	</head>
	<body>
		<div class='container'>
			<div id='logo'><img src='https://www.whogohost.com/assets/images/whlogo.png' height=50 /></div>
			<div id='body'>
				<p>Dear ".$name."</p>
				<p>Your Paystack transaction was unsuccessful. View details below</p>
				<p>Status: <strong>failure</strong></p>
				<p>Payment Reference: <strong>".$reference."</strong></p>
				<p>Error Message: <strong>".$message."</strong></p>
				<p>Amount: <strong>".$amount." </strong></p>
				<p>Invoice id: <strong>".$invid."</strong></p>

				<p>If you have any issues with this transaction, do not hesitate to send us a mail at billing@whogohost.com stating the transaction reference number.</p>
				<p>--------------------------------</p>
				<p>You can login to your client area at <a href='https://www.whogohost.com/host/viewinvoice.php?id=$invid'>https://www.whogohost.com/host/viewinvoice.php?id=$invid</a></p>
				<p>Whogohost</p>
			</div>
		</div>
		

	</body>
</html>";

$sendercontent =  "
<!Doctype html>
<html lang='en'>
	<head>
		<style>
		html, body{background: #EEE; margin: 0}
		.container{background: #FFF; max-width: 400px; margin: 0 auto; border: 1px solid #CCC; font-family: Arial; font-size: 12px; padding: 30px;}
		#logo{margin-bottom: 20px;}
		</style>
		
	</head>
	<body>
		<div class='container'>
			<div id='logo'><img src='https://www.whogohost.com/assets/images/whlogo.png' height=50 /></div>
			<div id='body'>
				<p>".$name." just attempted to pay for invoice ".$invid." but was unsuccessful</p>
				<p>Status: <strong>failure</strong></p>
				<p>Payment Reference: <strong>".$reference."</strong></p>
				<p>Error Message: <strong>".$message."</strong></p>
				<p>Amount: <strong>".$amount." </strong></p>
				<p>Invoice id: <strong>".$invid."</strong></p>

				<p>--------------------------------</p>
				<p><a href='https://www.whogohost.com/host/admin/invoices.php?action=edit&id={$invid}'>https://www.whogohost.com/host/admin/invoices.php?action=edit&id={$invid}</a></p>
			</div>
		</div>
		

	</body>
</html>";
			if (!isset($sender_name)){
				$sender_name = "Whogohost Billing";
			}
			if (!isset($sender_email)){
				$sender_email = "support@whogohost.com";
			}
			if (!isset($receiver_email)){
				$receiver_email = "tobaniyi@whogohost.com";
			}

            $headers  = 'MIME-Version: 1.0' . "\r\n";
			$headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
            $headers .= 'From: '.$sender_name.'<'.$sender_email.'>' . "\r\n";
			
			$subject = 'Failed Paystack Transaction Notification';
			// send email
			mail($email,$subject,$content,$headers);
            mail($receiver_email,$subject,$sendercontent,$headers);
}

function displaysucess($name,$amount,$fee,$invid,$reference,$url)
{
    echo "
<!Doctype html>
<html lang='en'>
	<head>
		<style>
		html, body{background: #EEE; margin: 0}
		.container{background: #FFF; max-width: 400px; margin: 0 auto; border: 1px solid #CCC; font-family: Arial; font-size: 12px; padding: 30px;}
		#logo{margin-bottom: 20px;}
		</style>
		<script type='text/javascript'>
		function inIframe () {
		    if ( window.location !== window.parent.location ) {
		    	javascript:window.parent.location.reload();
		    }else{
		    	window.location='/host/viewinvoice.php?id={$invid}';
		    }
		}
		</script>
		<title>Your transaction was successful</title>
	</head>
	<body>
		<div class='container'>
			<div id='logo'><img src='https://www.whogohost.com/assets/images/whlogo.png' height=50 /></div>
			<div id='body'>
				<p>Dear ".$name."</p>
				<p>Your Paystack transaction was successful. View details below</p>
				<p>Status: <strong>Approved</strong></p>
				<p>Payment Reference: <strong>".$reference."</strong></p>
				<p>Amount: <strong>".$amount."</strong> (Fee: ".$fee.")</p>
				<p>Invoice id: <strong>".$invid."</strong></p>
			</div>
			
			<p>
			  <strong>NOTE:</strong> A token has been stored on our system that will make it easier to pay for your next invoice. You may also automate the payment of all your invoices. <a href='https://www.whogohost.com/host/knowledgebase/352/How-does-it-work.html'>Visit this page</a> for more information about this. 
			</p>
			
			
			<p><strong>You may <a href='#' onclick='inIframe();'>click here</a> to complete the transaction. </strong></p>
		
		</div>
		

	</body>
</html>";
}

function displayfailure($name,$amount,$fee,$invid,$reference,$message,$url)
{
    echo "
<!Doctype html>
<html lang='en'>
	<head>
		<style>
		html, body{background: #EEE; margin: 0}
		.container{background: #FFF; max-width: 400px; margin: 0 auto; border: 1px solid #CCC; font-family: Arial; font-size: 12px; padding: 30px;}
		#logo{margin-bottom: 20px;}
		</style>
		<script type='text/javascript'>
		function inIframe () {
		    if ( window.location !== window.parent.location ) {
		    	javascript:window.parent.location.reload();
		    }else{
		    	window.location='/host/viewinvoice.php?id={$invid}';
		    }
		}
		</script>
		<title>Your transaction failed</title>
	</head>
	<body>
		<div class='container'>
			<div id='logo'><img src='https://www.whogohost.com/assets/images/whlogo.png' height=50 /></div>
			<div id='body'>
				<p>Dear ".$name."</p>
				<p>Your Paystack transaction was unsuccessful. View details below</p>
				<p>Status: <strong>failure</strong></p>
				<p>Payment Reference: <strong>".$reference."</strong></p>
				<p>Error Message: <strong>".$message."</strong></p>
				<p>Amount: <strong>".$amount." </strong>(Fee: $fee)</p>
				<p>Invoice id: <strong>".$invid."</strong></p>
				<p>If you have any issues with this transaction, do not hesitate to send us a mail at billing@whogohost.com stating the invoice id.</p>
			</div>
			<p><strong>You may <a href='#' onclick='inIframe();'>click here</a> to return to your invoice. </strong></p>
		
		</div>
		
		

	</body>
</html>";
}

?>