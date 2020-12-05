<?php
namespace yangyongxu\wxpay\server;

use yangyongxu\wxpay\server\lib\WxPayNotify;
use yangyongxu\wxpay\server\lib\WxPayException;
use yangyongxu\wxpay\server\lib\WxPayOrderQuery;
use yangyongxu\wxpay\server\lib\WxPayApi;

/**
 * 支付回调
 * @author YYX
 */
class WxNotify extends WxPayNotify{

	/**
	 * 支付回调
	 */
	public function NotifyProcess($objData, $config, &$msg){
		$data = $objData->GetValues();
		//TODO 1、进行参数校验
		if(!array_key_exists('return_code', $data)
		||(array_key_exists('return_code', $data) && $data['return_code'] != 'SUCCESS')) {
			//TODO失败,不是支付成功的通知
			//如果有需要可以做失败时候的一些清理处理，并且做一些监控
			Log::write('异常异常');
			$msg = '异常异常';
			return false;
		}
		if(!array_key_exists('transaction_id', $data)){
			Log::write('输入参数不正确');
			$msg = '输入参数不正确';
			return false;
			//查询订单，判断订单真实性return false;
		}elseif (!$this->Queryorder($data['transaction_id'])){
			Log::write('订单查询失败');
			$msg = '订单查询失败';
			return false;
		}

		//TODO 2、进行签名验证
		try {
			$checkResult = $objData->CheckSign($config);
			if($checkResult == false){
				//签名错误
				return false;
			}
		} catch(WxPayException $e) {
			return false;
		}
		//TODO 3、处理业务逻辑
		$attach = $data['attach'];
		return $this->$attach($data);
	}
	//查询订单
	protected function Queryorder($transaction_id)
	{
		$input = new WxPayOrderQuery();
		$input->SetTransaction_id($transaction_id);
		
		$result = WxPayApi::orderQuery($this->config, $input);
		Log::DEBUG('query:' . json_encode($result));
		if(array_key_exists('return_code', $result)
			&& array_key_exists('result_code', $result)
			&& $result['return_code'] == 'SUCCESS'
			&& $result['result_code'] == 'SUCCESS')
		{
			return true;
		}
		return false;
	}
}