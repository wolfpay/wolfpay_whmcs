<?php
use WHMCS\Database\Capsule;
function ensy($data, $key) {
    $key = md5($key);
    $len = strlen($data);
    $code = '';
    for ($i = 0; $i < ceil($len / 32); $i++) {
        for ($j = 0; $j < 32; $j++) {
            $p = $i * 32 + $j;
            if ($p < $len) {
                $code.= $data{$p} ^ $key{$j};
            }
        }
    }
    $code = str_replace(array(
        '+',
        '/',
        '='
    ) , array(
        '_',
        '$',
        ''
    ) , base64_encode($code));
    return $code;
}
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data) , '+/', '-_') , '=');
}
class Pay
{
    private $pid;
    private $key;
    private $api;

    public function __construct($pid, $key, $api)
    {
        $this->pid = $pid;
        $this->key = $key;
        $this->api = $api;
    }

    /**
     * @Note  支付发起
     * @param $type   支付方式
     * @param $out_trade_no     订单号
     * @param $notify_url     异步通知地址
     * @param $return_url     回调通知地址
     * @param $name     商品名称
     * @param $money     金额
     * @param $sitename     站点名称
     * @return string
     */
    public function submit($type, $out_trade_no, $notify_url, $return_url, $name, $money, $sitename)
    {
        $data = [
            'pid' => $this->pid,
            'type' => $type,
            'out_trade_no' => $out_trade_no,
            'notify_url' => $notify_url,
            'return_url' => $return_url,
            'name' => $name,
            'money' => $money,
            'sitename' => $sitename
        ];
        $string = http_build_query($data);
        $keys = ensy($string, $this->pid);
        $keyss = base64url_encode($this->pid . '-' . $keys);
        $sign = substr(ensy($keyss, $this->key) , 0, 15);
        return 'https://' . $this->api . '/submit?skey=' . $keyss . '&sign=' . $sign . '&sign_type=MD5';
    }
	 /**
     * @Note  退款发起
     * @param $trade_no     订单号
     */
    public function refund($trade_no) {
        $data = ['pid' => $this->pid, 'trade_no' => $trade_no];
        $string = http_build_query($data);
        $keys = ensy($string, $this->pid);
        $keyss = base64url_encode($this->pid . '-' . $keys);
        $sign = substr(ensy($keyss, $this->key) , 0, 15);
        return 'https://' . $this->api . '/refund?skey=' . $keyss . '&sign=' . $sign . '&sign_type=MD5';
        }

    /**
     * @Note   验证签名
     * @param $data  待验证参数
     * @return bool
     */
    public function verify($data)
    {
        if (!isset($data['sign']) || !$data['sign']) {
            return false;
        }
        $sign = $data['sign'];
        unset($data['sign']);
        unset($data['sign_type']);
        $sign2 = $this->getSign($data, $this->key);
        if ($sign != $sign2) {
            return false;
        }
        return true;
    }

    /**
     * @Note  生成签名
     * @param $data   参与签名的参数
     * @return string
     */
    private function getSign($data)
    {
        $data = array_filter($data);
        ksort($data);
        $str1 = '';
        foreach ($data as $k => $v) {
            $str1 .= '&' . $k . "=" . $v;
        }
        $str = $str1 . $this->key;
        $str = trim($str, '&');
        $sign = md5($str);
        return $sign;
    }
}
function wolfpay_MetaData() {
    return array(
        'DisplayName' => 'wolfpay',
        'APIVersion' => '3.0',
    );
}

function wolfpay_config() {
    $configarray = array(
        "FriendlyName"  => array(
            "Type"  => "System",
            "Value" => "wolfpay"
        ),
       "mchid" => array(
            "FriendlyName" => "商户ID",
            "Type"         => "text",
            "Size"         => "128",
        ),
        "key" => array(
            "FriendlyName" => "商户KEY",
            "Type"         => "text",
            "Size"         => "128",
        ),
        "api" => array(
            "FriendlyName" => "API網址",
            "Type"         => "text",
            "Size"         => "128",
        )
        
    );

    return $configarray;
}

function wolfpay_link($params) {
    if($_REQUEST['alipaysubmit'] == 'yes'){
	
	   $pay = new Pay($params['mchid'], $params['key'], $params['api']);

//支付方式
$type = 'all';

//订单号
$out_trade_no = $params['invoiceid'];

//异步通知地址
$notify_url = 'https://'.$_SERVER['HTTP_HOST'].'/modules/gateways/wolfpay/callback.php';

//回调通知地址
$return_url = $params['returnurl'];

//商品名称
$name = 'SS-'.$_SERVER['HTTP_HOST'];

//支付金额（保留小数点后两位）
$money = $params['amount'];

//站点名称
$sitename = $_SERVER['HTTP_HOST'];

//发起支付
$url = $pay->submit($type, $out_trade_no, $notify_url, $return_url, $name, $money, $sitename);
		$sHtml = "<script language='javascript' type='text/javascript'>window.location.href='".$url."';</script>";
	   exit($sHtml);
	}
    if(stristr($_SERVER['PHP_SELF'],'viewinvoice')){
		return '<form method="post" id=\'alipaysubmit\'><input type="hidden" name="alipaysubmit" value="yes"></form><button type="button" class="btn btn-danger btn-block" onclick="document.forms[\'alipaysubmit\'].submit()">使用wolfpay</button>';
    }else{
         return '<img style="width: 150px" src="'.$params['systemurl'].'/modules/gateways/wolfpay/alipay.png" alt="wolfpay" />';
    }

}
function wolfpay_refund($params)
{
   $pay = new Pay($params['mchid'], $params['key'], $params['api']);

//订单号
$trade_no = $params['transid'];

//发起支付
$url = $pay->submit($trade_no);
//init curl
$ch = curl_init();
//curl_setopt可以設定curl參數
//設定url
curl_setopt($ch , CURLOPT_URL , $url);
//執行，並將結果存回
$result = curl_exec($ch);
//關閉連線
curl_close($ch);
$arr=json_decode($result, true);
	if($arr['code']=='1'){
    return array(
        // 'success' if successful, otherwise 'declined', 'error' for failure
        'status' => 'success',
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $result,
        // Unique Transaction ID for the refund transaction
        'transid' => '0',
        // Optional fee amount for the fee value refunded
        'fee' => $params['amount'],
    );}else{return array(
        // 'success' if successful, otherwise 'declined', 'error' for failure
        'status' => 'error',
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $result,
        // Unique Transaction ID for the refund transaction
        'transid' => '0',
        // Optional fee amount for the fee value refunded
        'fee' => $params['amount'],
    );
	}
}
if(!function_exists("autogetamount")){
function autogetamount($params){
    $amount=$params['amount'];
    $currencyId=$params['currencyId'];
    $currencys=localAPI("GetCurrencies", [], wolfpay_getAdminname());
    if($currencys['result']=='success' and $currencys['totalresults']>=1){
        
    }else{
        var_dump($currencys);
        throw new \Exception('货币设置错误、API请求错误');
    }
    $currencys=$currencys['currencies']['currency'];
    foreach($currencys as $currency){
        if($currencyId==$currency['id']){
            $from=$currency;
            break;
        }
    }
    if(!$from){
        throw new \Exception("货币错误，找不到起始货币。");
    }
    foreach($currencys as $currency){
        $hb=strtoupper($currency['code']);
        if($hb=='TWD'){
            $cny=$currency;
            break;
        }
    }
    if(!$cny){
        throw new \Exception("找不到新台币货币，请确认后台货币中存在货币代码为TWD的货币！");
    }
    $rate=$cny['rate']/$from['rate'];
    return [round((double)$rate*$amount,2),round((double)$rate,2)];
}
}
if(!function_exists("wolfpay_getAdminname")){
function wolfpay_getAdminname(){
    $admin = Capsule::table('tbladmins')->first();
    return $admin->username;
}
}
