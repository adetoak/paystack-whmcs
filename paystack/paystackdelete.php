<?php
require_once 'functions.php';

$mcheck = md5($_GET['invoice'].$_GET['customerEmail'].$_GET['amount']);
$mcheck = strtoupper($mcheck);

if($mcheck != $_GET['mcheck']){
	die('Access Denied');
}

$result = paystack_delete_gatewayid($_GET['userid']);

if ($result){	
	echo "<!Doctype html>
	<html lang='en'>
		<head>
			<style>
			html, body{background: #EEE; margin: 0}
			.container{background: #FFF; max-width: 400px; margin: 0 auto; border: 1px solid #DDD; font-family: Arial; font-size: 12px; padding: 30px;}
			#logo{margin-bottom: 20px;}
			</style>
			<script type='text/javascript'>
			function inIframe () {
			    if ( window.location !== window.parent.location ) {
			    	javascript:window.parent.location.reload();
			    }else{
			    	window.location='/host/viewinvoice.php?id={$_GET['invoice']}';
			    }
			}
			</script>
		</head>
		<body>
			<div class='container'>
				<div id='logo'><img src='https://paystack.com/assets/website/images/brand/logo/two-toned.png' height=50 /></div>
				<div id='body'>
					<p>You have successfully unsubscribed from Paystack. <a href='#'' onclick='inIframe();''>Click here to return</a></p>
				</div>
			</div>
			

		</body>
	</html>";
}
?>