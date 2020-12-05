<?php
namespace yangyongxu\wxpay\base\lib;

use yangyongxu\wxpay\base\lib\data\WxPayResults;
use yangyongxu\wxpay\base\lib\data\WxPayReport;

/**
 * 提现
 * @author YYX
 */
class WxPayCashOut extends WxPayResults
{
	/**
	 * SDK版本号
	 * @var string
	 */
	public static $VERSION = "3.0.10";
	
	/**
	 * 生成签名
	 */
	public function MakeSign($config, $needSignType = false){
		if($needSignType) {
			$this->SetSignType($config->GetSignType());
		}
		//签名步骤一：按字典序排序参数
		ksort($this->values);
		$string = $this->ToUrlParams();
		//签名步骤二：在string后加入KEY
		$string = $string . "&key=".$config->GetKey();
		//签名步骤三：MD5加密
		//$string = md5($string);
		if($config->GetSignType() == "MD5"){
			$string = md5($string);
		} else if($config->GetSignType() == "HMAC-SHA256") {
			$string = hash_hmac("sha256",$string ,$config->GetKey());
		} else {
			throw new WxPayException("签名类型不支持！");
		}
		//签名步骤四：所有字符转为大写
		$this->values['sign'] = strtoupper($string);
	}
	
	/**
	 *
	 * 上报数据， 上报的时候将屏蔽所有异常流程
	 * @param WxPayConfigInterface $config  配置对象
	 * @param string $usrl
	 * @param int $startTimeStamp
	 * @param array $data
	 */
	public static function reportCostTime($config, $url, $startTimeStamp, $data)
	{
		//如果不需要上报数据
		$reportLevenl = $config->GetReportLevenl();
		if($reportLevenl == 0){
			return;
		}
		//如果仅失败上报
		if($reportLevenl == 1 &&
				array_key_exists("return_code", $data) &&
				$data["return_code"] == "SUCCESS" &&
				array_key_exists("result_code", $data) &&
				$data["result_code"] == "SUCCESS")
		{
			return;
		}
			
		//上报逻辑
		$endTimeStamp = self::getMillisecond();
		$objInput = new WxPayReport();
		$objInput->SetInterface_url($url);
		$objInput->SetExecute_time_($endTimeStamp - $startTimeStamp);
		//返回状态码
		if(array_key_exists("return_code", $data)){
			$objInput->SetReturn_code($data["return_code"]);
		}
		//返回信息
		if(array_key_exists("return_msg", $data)){
			$objInput->SetReturn_msg($data["return_msg"]);
		}
		//业务结果
		if(array_key_exists("result_code", $data)){
			$objInput->SetResult_code($data["result_code"]);
		}
		//错误代码
		if(array_key_exists("err_code", $data)){
			$objInput->SetErr_code($data["err_code"]);
		}
		//错误代码描述
		if(array_key_exists("err_code_des", $data)){
			$objInput->SetErr_code_des($data["err_code_des"]);
		}
		//商户订单号
		if(array_key_exists("out_trade_no", $data)){
			$objInput->SetOut_trade_no($data["out_trade_no"]);
		}
		//设备号
		if(array_key_exists("device_info", $data)){
			$objInput->SetDevice_info($data["device_info"]);
		}
	
		try{
			self::report($config, $objInput);
		} catch (WxPayException $e){
			//不做任何处理
		}
	}
	
	/**
	 *
	 * 测速上报，该方法内部封装在report中，使用时请注意异常流程
	 * WxPayReport中interface_url、return_code、result_code、user_ip、execute_time_必填
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WxPayConfigInterface $config  配置对象
	 * @param WxPayReport $inputObj
	 * @param int $timeOut
	 * @throws WxPayException
	 * @return 成功时返回，其他抛异常
	 */
	public static function report($config, $inputObj, $timeOut = 1)
	{
		$url = "https://api.mch.weixin.qq.com/payitil/report";
		//检测必填参数
		if(!$inputObj->IsInterface_urlSet()) {
			throw new WxPayException("接口URL，缺少必填参数interface_url！");
		} if(!$inputObj->IsReturn_codeSet()) {
			throw new WxPayException("返回状态码，缺少必填参数return_code！");
		} if(!$inputObj->IsResult_codeSet()) {
			throw new WxPayException("业务结果，缺少必填参数result_code！");
		} if(!$inputObj->IsUser_ipSet()) {
			throw new WxPayException("访问接口IP，缺少必填参数user_ip！");
		} if(!$inputObj->IsExecute_time_Set()) {
			throw new WxPayException("接口耗时，缺少必填参数execute_time_！");
		}
		$inputObj->SetAppid($config->GetAppId());//公众账号ID
		$inputObj->SetMch_id($config->GetMerchantId());//商户号
		$inputObj->SetUser_ip($_SERVER['REMOTE_ADDR']);//终端ip
		$inputObj->SetTime(date("YmdHis"));//商户上报时间
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
	
		$inputObj->SetSign($config);//签名
		$xml = $inputObj->ToXml();
	
		$startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($config, $xml, $url, false, $timeOut);
		return $response;
	}
	
	/**
	 *
	 * 产生随机字符串，不长于32位
	 * @param int $length
	 * @return 产生的随机字符串
	 */
	public static function getNonceStr($length = 32)
	{
		$chars = "abcdefghijklmnopqrstuvwxyz0123456789";
		$str ="";
		for ( $i = 0; $i < $length; $i++ )  {
			$str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);
		}
		return $str;
	}
	/**
	 * 以post方式提交xml到对应的接口url
	 *
	 * @param WxPayConfigInterface $config  配置对象
	 * @param string $xml  需要post的xml数据
	 * @param string $url  url
	 * @param bool $useCert 是否需要证书，默认不需要
	 * @param int $second   url执行超时时间，默认30s
	 * @throws WxPayException
	 */
	public static function postXmlCurl($config, $xml, $url, $useCert = false, $second = 30)
	{
		$ch = curl_init();
		$curlVersion = curl_version();
		$ua = "WXPaySDK/".self::$VERSION." (".PHP_OS.") PHP/".PHP_VERSION." CURL/".$curlVersion['version']." "
				.$config->GetMerchantId();
	
		//设置超时
		curl_setopt($ch, CURLOPT_TIMEOUT, $second);
	
		$proxyHost = "0.0.0.0";
		$proxyPort = 0;
		$config->GetProxy($proxyHost, $proxyPort);
		//如果有配置代理这里就设置代理
		if($proxyHost != "0.0.0.0" && $proxyPort != 0){
			curl_setopt($ch,CURLOPT_PROXY, $proxyHost);
			curl_setopt($ch,CURLOPT_PROXYPORT, $proxyPort);
		}
		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,TRUE);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);//严格校验
		curl_setopt($ch,CURLOPT_USERAGENT, $ua);
		//设置header
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		//要求结果为字符串且输出到屏幕上
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	
		if($useCert == true){
			//设置证书
			//使用证书：cert 与 key 分别属于两个.pem文件
			//证书文件请放入服务器的非web目录下
			$sslCertPath = "";
			$sslKeyPath = "";
			$config->GetSSLCertPath($sslCertPath, $sslKeyPath);
			curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLCERT, $sslCertPath);
			curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLKEY, $sslKeyPath);
		}
		//post提交方式
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		//运行curl
		$data = curl_exec($ch);
		//返回结果
		if($data){
			curl_close($ch);
			return $data;
		} else {
			$error = curl_errno($ch);
			curl_close($ch);
			throw new WxPayException("curl出错，错误码:$error");
		}
	}
	/**
	 * 获取毫秒级别的时间戳
	 */
	public static function getMillisecond()
	{
		//获取毫秒的时间戳
		$time = explode ( " ", microtime () );
		$time = $time[1] . ($time[0] * 1000);
		$time2 = explode( ".", $time );
		$time = $time2[0];
		return $time;
	}
}

