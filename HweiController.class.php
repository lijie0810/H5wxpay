<?php

namespace Home\Controller;

use Think\Controller;

header('Content-type:text/html;charset=utf-8');

class Hweiontroller extends Controller {

	private $app_id = ''; //appid
	private $mch_id = ''; //商户号
	private $makesign = ''; //支付的签名(在商户平台API安全按钮中获取)
	private $notify = ''; //回调地址
	private $return_url = ''; //  返回地址
	private $app_secret = ''; //app_secret
	public $error = 0;
	public $orderid = null;
	public $openid = '';

	//进行微信支付
	public function wxpay() {
		echo "<h1 style='text-align:center;margin-top:50%;'>开启请联系管理员</h1>";die;
//        echo this->is_weixin();
		if ($this->is_weixin() == true) {
			exit("<script>alert('请在手机wap浏览器尝试');document.location.href='https://m.12348.com.cn/';</script>");
		}
		$reannumb = date("YmdHis") . rand(10000, 9999);
		$pays = 0.01; //获取需要支付的价格
		#插入语句书写的地方
		$conf = $this->payconfig($reannumb, $pays * 100, '费用支付');
		if (!$conf || $conf['return_code'] == 'FAIL') {
			exit("<script>alert('对不起，微信支付接口调用错误!" . $conf['return_msg'] . "');history.go(-1);</script>");
		}
		$conf['mweb_url'] = $conf['mweb_url'] . '&redirect_url=' . urlencode($this->return_url);
		$this->assign('parameters', $conf['mweb_url']);
		$this->display('h5'); //这是模板
	}
	public function return_wx() {
		exit('SUCCESS'); //打死不能去掉
	}
	//////////////////////////////////////////////////////////////////////////////
	/////              下面都是配置参数尽量不需要动的                          /////////
	//////////////////////////////////////////////////////////////////////////////
	#微信JS支付参数获取#
	protected function payconfig($no, $fee, $body) {
		$url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
		$data['appid'] = $this->app_id;
		$data['mch_id'] = $this->mch_id; //商户号
		$data['device_info'] = 'WEB';
		$data['nonce_str'] = $this->createNoncestr();
		$data['body'] = $body;
		$data['out_trade_no'] = $no; //订单号
		$data['total_fee'] = $fee; //金额
		//$data['spbill_create_ip'] = $_SERVER["REMOTE_ADDR"];  //ip地址
		$data['notify_url'] = $this->notify; //回调地址不能带参数
		$data['return_url'] = $this->return_url; //返回地址不能带参数 (占时不需要放开)
		$data['trade_type'] = 'MWEB';
		$data['spbill_create_ip'] = get_client_ip();
		$data['scene_info'] = '{"h5_info":{"type":"Wap","wap_url":"http://www.baidu.com","wap_name": "描述"}}';
		$data['sign'] = $this->MakeSign($data);
		//var_dump($data);
		$xml = $this->ToXml($data);
		$curl = curl_init(); // 启动一个CURL会话
		curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
		//设置header
		curl_setopt($curl, CURLOPT_HEADER, FALSE);
		//要求结果为字符串且输出到屏幕上
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_POST, TRUE); // 发送一个常规的Post请求
		curl_setopt($curl, CURLOPT_POSTFIELDS, $xml); // Post提交的数据包
		curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
		$tmpInfo = curl_exec($curl); // 执行操作
		curl_close($curl); // 关闭CURL会话
		$arr = $this->FromXml($tmpInfo);
		return $arr;
	}

	/**
	 *    作用：产生随机字符串，不长于32位
	 */
	public function createNoncestr($length = 32) {
		$chars = "abcdefghijklmnopqrstuvwxyz0123456789";
		$str = "";
		for ($i = 0; $i < $length; $i++) {
			$str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
		}
		return $str;
	}

	/**
	 *    作用：产生随机字符串，不长于32位
	 */
	public function randomkeys($length) {
		$pattern = '1234567890123456789012345678905678901234';
		$key = null;
		for ($i = 0; $i < $length; $i++) {
			$key .= $pattern{mt_rand(0, 30)}; //生成php随机数
		}
		return $key;
	}

	/**
	 * 将xml转为array
	 * @param string $xml
	 * @throws WxPayException
	 */
	public function FromXml($xml) {
		//将XML转为array
		return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
	}

	/**
	 * 输出xml字符
	 * @throws WxPayException
	 * */
	public function ToXml($arr) {
		$xml = "<xml>";
		foreach ($arr as $key => $val) {
			if (is_numeric($val)) {
				$xml .= "<" . $key . ">" . $val . "</" . $key . ">";
			} else {
				$xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
			}
		}
		$xml .= "</xml>";
		return $xml;
	}

	/**
	 * 生成签名
	 * @return 签名，本函数不覆盖sign成员变量，如要设置签名需要调用SetSign方法赋值
	 */
	protected function MakeSign($arr) {
		//签名步骤一：按字典序排序参数
		ksort($arr);
		$string = $this->ToUrlParams($arr);
		//签名步骤二：在string后加入KEY
		$string = $string . "&key=" . $this->makesign;
		//签名步骤三：MD5加密
		$string = md5($string);
		//签名步骤四：所有字符转为大写
		$result = strtoupper($string);
		return $result;
	}

	/**
	 * 格式化参数格式化成url参数
	 */
	protected function ToUrlParams($arr) {
		$buff = "";
		foreach ($arr as $k => $v) {
			if ($k != "sign" && $v != "" && !is_array($v)) {
				$buff .= $k . "=" . $v . "&";
			}
		}
		$buff = trim($buff, "&");
		return $buff;
	}
	public function get_client_ip() {
		$cip = 'unknown';
		if ($_SERVER['REMOTE_ADDR']) {
			$cip = $_SERVER['REMOTE_ADDR'];
		} elseif (getenv('REMOTE_ADDR')) {
			$cip = getenv('REMOTE_ADDR');
		}
		return $cip;
	}
	//判断是不是在微信浏览器中
	public function is_weixin() {
		if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
			return true;
		}
		return false;
	}
	public function notify_wx() {
//       file_put_contents('sucaihuo_h5.txt', date("Y-m-d H:i:s") . "：" . $a);
		$xml = file_get_contents("php://input");
		$log = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
		$a = json_encode($log);
		$ids = $log['out_trade_no']; //获取订单号
		$ids = str_replace('Bm', '', $ids); //去掉 支付前缀
		file_put_contents('sucaihuo_h5.txt', date("Y-m-d H:i:s") . ",微信h5支付回调信息：" . $a);
		exit('SUCCESS'); //打死不能去掉
	}

}
