<?php
/**
 ** ***************************************************************** **\
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
\
************************************************************************/

if (!defined("WHMCS")) {
    die("<!-- Silence. SHHHHH!!!! -->");
}

/**
 * Define Paystack gateway configuration options.
 *
 * @return array
 */
function paystack_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Paystack (Debit/Credit Cards)'
        ),
        'whmcsurl' => array(
            'FriendlyName' => 'Whmcs Url',
            'Type' => 'text',
            'Value' => 'Website Url'
        ),
        'sitename' => array(
            'FriendlyName' => 'Site Name',
            'Type' => 'text',
            'Value' => 'Website Name'
        ),
        'gatewayLogs' => array(
            'FriendlyName' => 'Gateway logs',
            'Type' => 'yesno',
            'Description' => 'Tick to enable gateway logs',
            'Default' => '0'
        ),
        'testMode' => array(
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable test mode',
            'Default' => '0'
        ),
        'liveSecretKey' => array(
            'FriendlyName' => 'Live Secret Key',
            'Type' => 'text',
            'Size' => '32',
            'Default' => 'sk_live_xxx'
        ),
        'livePublicKey' => array(
            'FriendlyName' => 'Live Public Key',
            'Type' => 'text',
            'Size' => '32',
            'Default' => 'pk_live_xxx'
        ),
        'testSecretKey' => array(
            'FriendlyName' => 'Test Secrect Key',
            'Type' => 'text',
            'Size' => '32',
            'Default' => 'sk_test_xxx'
        ),
        'testPublicKey' => array(
            'FriendlyName' => 'Test Public Key',
            'Type' => 'text',
            'Size' => '32',
            'Default' => 'pk_test_xxx'
        ),
        'whmcsuser' => array(
            'FriendlyName' => 'WHMCS Username',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter your WHMCS API Username here',
        ),
        'senderemail' => array(
            'FriendlyName' => 'Sender Email',
            'Type' => 'text',
            'Value' => 'Sender Email'
        )
    );
}

function paystack_nolocalcc(){}

function paystack_adminstatusmsg($vars) {
    $gatewayProfileID = get_query_val(
        'paystackpy',
        'gatewayid',
        array('custid' => $vars['userid'])
    );
    return array(
        'type' => 'info',
        'title' => 'Gateway Profile',
        'msg' => ($gatewayProfileID) ? 'This client has a gateway profile with code ' . $gatewayProfileID : 'This client does not yet have a gateway profile setup'
    ); 
}

/**
 * Payment link.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @return string
 */
function paystack_link($params) 
{
    require_once dirname(__FILE__) . '/paystack/functions.php';
    // Client    
    $email = $params['clientdetails']['email'];
    $customername = $params['clientdetails']['fullname'];
    $phone = $params['clientdetails']['phonenumber'];
    $userid = $params['clientdetails']['userid'];
    $params['langpaynow'] = 
        array_key_exists('langpaynow', $params) ? 
            $params['langpaynow'] : 'Pay with ATM' ;

    // Config Options
    if ($params['testMode'] == 'on') {
        $publicKey = $params['testPublicKey'];
        $secretKey = $params['testSecretKey'];
    } else {
        $publicKey = $params['livePublicKey'];
        $secretKey = $params['liveSecretKey'];
    }
    
    // check if there is an id in the GET meaning the invoice was loaded directly
    $paynowload = ( !array_key_exists('id', $_GET) );
    
    // Invoice
    $invoiceId = $params['invoiceid'];
    $amountinkobo = intval(floatval($params['amount'])*100);
    $currency = $params['currency'];

    if (!(strtoupper($currency) == 'NGN')) {
        return ("Paystack only accepts NGN payments for now.");
    }
    
    $isSSL = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443);
    $fallbackUrl = 'http' . ($isSSL ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] .
        substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], '/')) .
        '/modules/gateways/callback/paystack.php?' . 
        http_build_query(array(
            'invoiceid'=>$invoiceId,
            'email'=>$email,
            'phone'=>$phone,
            'amountinkobo'=>$amountinkobo,
            'customername'=>$customername,
            'go'=>'standard'
        ));

    $callbackUrl = 'http' . ($isSSL ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] .
        substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], '/')) .
        '/modules/gateways/callback/paystack.php?' . 
        http_build_query(array(
            'invoiceid'=>$invoiceId,
            'email'=>$email,
            'phone'=>$phone,
            'amountinkobo'=>$amountinkobo,
            'customername'=>$customername
        ));

    $amount = $params['amount'];
    $t=time();
    $mcheck = md5($invoiceId.$email.$amount);
    $mcheck = strtoupper($mcheck);
    $paystack_logo_url = 'https://paystack.com/assets/website/images/brand/logo/two-toned.png';    

    $gatewayid = paystack_get_gatewayid($userid);   

    /*var_dump($gatewayid);     
    die();*/

    if( !empty( $gatewayid ) && ($gatewayid != false) ){
        $switch = $params['whmcs_url'].'modules/gateways/paystack/paystackrecurring.php?name='.$customername.'&whmcs='.$params['whmcsurl'].
        '&authcode='.$gatewayid[0].'&amount='.$amount.'&customerEmail='.$email.'&invoice='.$params['invoiceid'].'&mcheck='.$mcheck;    
        
         $code = '
         <p><img style="width:140px;" src="'.$paystack_logo_url.'" /></p>
         <a class="btn btn-primary" href="' . $switch . '" id="paystack" onclick="myFunction();">Pay with paystack</a> &nbsp; <a class="btn btn-danger" href="'.$params['whmcs_url'].'modules/gateways/paystack/paystackdelete.php?customerEmail='.$email.'&name='.urlencode($customername).'&invoice='.$params['invoiceid'].'&userid='.$userid.'&amount='.$amount.'&mcheck='.$mcheck.'" onclick="return confirm(\'Are you sure you want to unsubscribe from paystack?\')">Unsubscribe</a><p><small><a href="'.$params['whmcs_url'].'modules/gateways/paystack/paystackdelete.php?customerEmail='.$email.'&name='.urlencode($customername).'&invoice='.$params['invoiceid'].'&userid='.$userid.'&amount='.$amount.'&mcheck='.$mcheck.'" onclick="return confirm(\'Are you sure you want to unsubscribe from paystack?\')">Unsubscribe if you want to change your card details</a></small></p>
         <script>           

            function myFunction(){
                var date = new Date();
                date.setTime(date.getTime()+(3600*1000));
                var expire = "; expires="+date.toGMTString();               
                document.cookie = "username=paystack Cookie; expires="+expire+" UTC; path=/";
                document.getElementById("paystack").classList.add("disabled");
                document.getElementById("paystack").disabled = true;             

            };
         </script>';
    }else{
        $code = '<form target="hiddenIFrame" action="about:blank">
            <script src="https://js.paystack.co/v1/inline.js"></script>
            <div class="payment-btn-container2"></div>
            <script>
                // load jQuery 1.12.3 if not loaded
                (typeof $ === \'undefined\') && document.write("<scr" + "ipt type=\"text\/javascript\" '.
                'src=\"https:\/\/code.jquery.com\/jquery-1.12.3.min.js\"><\/scr" + "ipt>");
            </script>
            <script>
                $(function() {
                    var paymentMethod = $(\'select[name="gateway"]\').val();
                    if (paymentMethod === \'paystack\') {
                        $(\'.payment-btn-container2\').hide();
                        var toAppend = \'<button type="button"'. 
                       ' onclick="payWithPaystack()" class="btn btn-success"> '.addslashes($params['langpaynow']).'</button>\';
                        $(\'.payment-btn-container\').append(toAppend);
                       if($(\'.payment-btn-container\').length===0){
                         $(\'select[name="gateway"]\').after(toAppend);
                       }
                    }
                });
            </script>
        </form>
        <div class="hidden" style="display:none"><iframe name="hiddenIFrame"></iframe></div>
        <script>
            var paystackIframeOpened = false;
            var paystackHandler = PaystackPop.setup({
              key: \''.addslashes(trim($publicKey)).'\',
              email: \''.addslashes(trim($email)).'\',
              phone: \''.addslashes(trim($phone)).'\',
              amount: '.$amountinkobo.',
              callback: function(response){
                window.location.href = \''.addslashes($callbackUrl).'&trxref=\' + response.trxref;
              },
              onClose: function(){
                  paystackIframeOpened = false;
              }
            });
            function payWithPaystack(){
                if (paystackHandler.fallback || paystackIframeOpened) {
                  // Handle non-support of iframes or
                  // Being able to click PayWithPaystack even though iframe already open
                  window.location.href = \''.addslashes($fallbackUrl).'\';
                } else {
                  paystackHandler.openIframe();
                  paystackIframeOpened = true;
                  $(\'img[alt="Loading"]\').hide();
                  $(\'div.alert.alert-info.text-center\').html(\'Click the button below to retry payment...\');
                  $(\'.payment-btn-container2\').append(\'<button type="button"'. 
                    ' onclick="payWithPaystack()">'.addslashes($params['langpaynow']).'</button>\');
                }
           }
           ' . ( $paynowload ? 'setTimeout("payWithPaystack()", 5100);' : '' ) . '
        </script>';        
    }


    return $code;
}

