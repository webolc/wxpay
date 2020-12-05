<?php
namespace yangyongxu\wxpay\server;

use yangyongxu\wxpay\server\lib\WxPayException;
use yangyongxu\wxpay\server\lib\WxPayCashOut AS CashOut;

class WxPayCashOut
{
	public $config;
	public function __construct($config){
		$this->config = new WxPayConfig($config);
	}
    /**
     * [提现到微信零钱]
     * @param  [String] $openid             [用户openid]
     * @param  [String] $true_name          [用户姓名]
     * @param  [int]    $amount             [企业付款金额，单位为分]
     * @param  [String] $partner_trade_no   [商户订单号，需保持唯一性(只能是字母或者数字，不能包含有符号)]
     */
	public function transfer($openid, $true_name, $amount, $partner_trade_no ,$timeOut = 6,$check_name='NO_CHECK',$desc='提现成功!'){
    	$url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers";
    
    	$transfer = new CashOut();
    	$data = [
	    	'mch_appid'        => $this->config->GetAppId(),
	    	'mchid'            => $this->config->GetMerchantId(),
	    	'nonce_str'        => $transfer::getNonceStr(),
	    	'partner_trade_no' => $partner_trade_no,
    		'check_name'       => $check_name,//'NO_CHECK',//FORCE_CHECK
	    	'amount'           => $amount*100,
    		'desc'             => $desc,
	    	'spbill_create_ip' => $_SERVER["REMOTE_ADDR"]
    	];
    	if ($openid){
    		$data['openid'] = $openid;
    	}
    	if ($true_name){
    		$data['re_user_name'] = $true_name;
    	}
    
    	try {
    		//签名
    		$transfer->FromArray($data);
    		$transfer->MakeSign($this->config);
    		$xml = $transfer->ToXml();
    		$response = $transfer->postXmlCurl($this->config, $xml, $url, true, $timeOut);
    		$transfer->FromXml($response);
    		return success('',$transfer->GetValues());
    	} catch (WxPayException $e){
    		$msg = $e->errorMessage();
    		return error($msg);
    	}
    }
    /**
     *
     * [到银行卡]
     * @param  [String] $bank_no            [收款方银行卡号]
     * @param  [String] $true_name          [收款方用户名]
     * @param  [String] $bank_code          [收款方开户行ID]
     * @param  [int]    $amount             [企业付款金额，单位为分]
     * @param  [String] $partner_trade_no   [商户订单号，需保持唯一性(
     */
    public function paybank($bank_no, $true_name, $bank_code, $amount, $partner_trade_no,$timeOut=6,$desc='提现成功!')
    {
    	$url = "https://api.mch.weixin.qq.com/mmpaysptrans/pay_bank";
    	$paybank = new CashOut();
        $data = [
            'mch_id'            => $this->config->GetMerchantId(),
            'partner_trade_no'  => $partner_trade_no,
            'nonce_str'         => $paybank::getNonceStr(),
            'enc_bank_no'       => $this->getRSA($bank_no),
            'enc_true_name'     => $this->getRSA($true_name),
            'bank_code'         => $bank_code,
            'amount'            => $amount*100,
        	'desc'              => $desc
        ];

        try {

        	$paybank->FromArray($data);
        	//签名
        	$paybank->MakeSign($config);
        	$xml = $paybank->ToXml();
        	
        	$startTimeStamp = CashOut::getMillisecond();//请求开始时间
        	$response = CashOut::postXmlCurl($config, $xml, $url, false, $timeOut);
        	$result = CashOut::Init($config, $response);
        	self::reportCostTime($config, $url, $startTimeStamp, $result);//上报请求花费时间
        	return success('',$result);
       	} catch (WxPayException $e){
       		$msg = $e->errorMessage();
       		return error($msg);
       	}
    }
    protected function getRSA($string){
    	$sslCertPath = '';
    	$sslKeyPath = '';
    	$this->config->GetSSLCertPath($sslCertPath, $sslKeyPath);
    	$publicKey = file_get_contents($sslKeyPath);
    	$encryptedBlock = '';
    	openssl_public_encrypt($string,$encryptedBlock, $publicKey, OPENSSL_PKCS1_OAEP_PADDING);
    	return base64_encode($encryptedBlock);
    }
}
