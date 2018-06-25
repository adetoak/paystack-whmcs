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


?>