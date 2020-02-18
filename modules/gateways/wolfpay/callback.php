<?php
use WHMCS\Database\Capsule;
include("../../../init.php");
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");

$gatewaymodule = "wolfpay";
$GATEWAY       = getGatewayVariables($gatewaymodule);

if (!$GATEWAY["type"]) die("fail");
$pay = new Pay($GATEWAY['mchid'], $GATEWAY['key'], $GATEWAY['api']);

$data = $_GET;

$out_trade_no = $data['out_trade_no'];

if ($pay->verify($data)) {
    if ($data['trade_status'] == 'TRADE_SUCCESS') {
    
		$invoiceid = checkCbInvoiceID($out_trade_no, $GATEWAY["name"]);
    checkCbTransID($out_trade_no);
	function convert_helper($invoiceid,$amount){
    $setting = Capsule::table("tblpaymentgateways")->where("gateway",$gatewaymodule)->where("setting","convertto")->first();

    if (empty($setting)){ return $amount; }
    
    $data = Capsule::table("tblinvoices")->where("id",$invoiceid)->get()[0];
    $userid = $data->userid;
    $currency = getCurrency( $userid );

    return  convertCurrency( $amount , $setting->value  ,$currency["id"] );
}
	  $amount = convert_helper( $invoiceid, $fee);
    addInvoicePayment($invoiceid,$data['trade_no'],trim($amount),$fee,$typess);
    logTransaction($GATEWAY["name"], $_REQUEST, "Successful");
    echo 'success';
    }
} else {
    echo '錯誤';
}
