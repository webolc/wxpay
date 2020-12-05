<?php
namespace yangyongxu\wxpay\server;

use yangyongxu\wxpay\server\lib\WxPayApi;
use yangyongxu\wxpay\server\lib\data\WxPayUnifiedOrder;
use yangyongxu\wxpay\server\lib\WxPayException;
use yangyongxu\wxpay\server\lib\WxPayJsApiPay;
use yangyongxu\wxpay\server\lib\WxPayBizPayUrl;

/**
 * 支付基础类
 * @author YYX
 */
class Wxpay{
	
	public $config;
	protected $basePay;
	protected $baseData;
	
	public function __construct($config){
		$this->config = new WxPayConfig($config);
		$this->basePay = new WxPayApi();
		$this->baseData = new WxPayUnifiedOrder();
	}
	
	/**
	 * JSAPI支付
	 * @author YYX
	 */
	public function JsApiPay($data){
		$jsapi = new WxPayJsApiPay();
		$this->_setData($data,'JSAPI');
		try {
			$order = $this->basePay->unifiedOrder($this->config,$this->baseData);
			if ($order['return_code'] == 'FAIL'){
				return false;
			}
			$jsapi->SetAppid($this->baseData->GetAppid());
			$jsapi->SetTimeStamp(strval(time()));
			$jsapi->SetNonceStr($this->basePay->getNonceStr());
			$prepay_id = isset($order['prepay_id'])?$order['prepay_id']:'';
			$jsapi->SetPackage("prepay_id=".$prepay_id);
			$jsapi->SetPaySign($jsapi->MakeSign($this->config));
			
			$parameters = json_encode($jsapi->GetValues());
			return $parameters;
		} catch (WxPayException $e){
			$msg = $e->errorMessage();
			return false;
		}
	}
	
	/**
	 * Native支付
	 * @author YYX
	 */
	public function NativePay($data){
		$native = new WxPayBizPayUrl();
		$this->_setData($data,'NATIVE');
		try {
			$order = $this->basePay->unifiedOrder($this->config,$this->baseData);
			if ($order['return_code'] == 'FAIL'){
				return false;
			}
			$order['ewm_url'] = isset($order['code_url'])?'https://api.lmyyst.com/ewm/'.urlencode(base64_encode($order['code_url'])).'.png':'';
			return success('获取成功',$order);
		} catch (WxPayException $e){
			$msg = $e->errorMessage();
			return error($msg);
		}
	}
	/**
	 * 配置支付数据
	 */
	protected function _setData($data,$type){
		//支付方式
		$this->baseData->SetTrade_type($type);
		//设置订单过期时间
		$this->baseData->SetTime_expire(date('YmdHis',($data['time']+(2*60*60))));
		//设置APP和网页支付提交用户端ip，Native支付填调用微信支付API的机器IP。
		$this->baseData->SetSpbill_create_ip(get_client_ip());
		//设置订单金额
		$this->baseData->SetTotal_fee($data['fee']*100);
		
		//设置附加数据
		if (isset($data['attach']))
			$this->baseData->SetAttach($data['attach']);
		
		//设置商品或支付单简要描述
		if (isset($data['body']))
			$this->baseData->SetBody($data['body']);
		
		//设置商品名称明细列表
		if (isset($data['detail']))
			$this->baseData->SetDetail($data['detail']);
		
		//设置商户系统内部的订单号,32个字符内
		if (isset($data['out_trade_no']))
			$this->baseData->SetOut_trade_no($data['out_trade_no']);
		
		//获取trade_type=NATIVE，此参数必传。此id为二维码中包含的商品ID
		if (isset($data['product_id']))
			$this->baseData->SetProduct_id($data['product_id']);
		
		//设置trade_type=JSAPI，此参数必传，用户在商户appid下的唯一标识。下单前需要调用【网页授权获取用户信息】接口获取到用户的Openid
		if (isset($data['openid']))
			$this->baseData->SetOpenid($data['openid']);
	}
}