<?php
namespace yangyongxu\wxpay\server;

use yangyongxu\wxpay\server\lib\WxPayApi;
use yangyongxu\wxpay\server\lib\WxPayException;
use yangyongxu\wxpay\server\lib\data\WxPayRefund AS Refund;
use yangyongxu\wxpay\server\lib\data\WxPayUnifiedOrder;

class WxPayRefund
{
	public $config;
	protected $basePay;
	protected $baseData;
	
	public function __construct($config){
		$this->config = new WxPayConfig($config);
		$this->basePay = new WxPayApi();
		$this->baseData = new WxPayUnifiedOrder();
	}
    /**
     * [订单退款]
     * @param  [String] $transaction_id    [微信订单号]
     * @param  [December] $out_refund_no   [系统订单号]
     * @param  [December] $total_fee       [订单总金额]
     * @param  [String] $refund_fee        [退款金额]
     */
    public function index($transaction_id, $out_refund_no, $total_fee, $refund_fee,$op_user_id='')
    {
        $op_user_id= $op_user_id?$op_user_id:$this->config->GetMerchantId();
        
        $inputObj = new Refund();
        $inputObj->SetOut_refund_no($out_refund_no);
        $inputObj->SetOut_trade_no($transaction_id);
        $inputObj->SetRefund_fee($refund_fee*100);
        $inputObj->SetTotal_fee($total_fee*100);
        $inputObj->SetOp_user_id($op_user_id);
        try {
	       	return success('',$this->basePay->refund($this->config, $inputObj));
       	} catch (WxPayException $e){
       		$msg = $e->errorMessage();
       		return error($msg);
       	}
    }
}
