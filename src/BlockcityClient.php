<?php

namespace ipfsmainofficial\blockcity;

/**
 * blockcity api client
 */
use linslin\yii2\curl;
use yii\base\Exception;

class BlockcityClient
{
    public $gateway;//网关
    public $auth_url;//授权地址
    public $client_id;//客户端id
    public $client_secret;//客户端密钥
    public $rsa_private_key_file;//私钥文件
    public $pay_expire = '30m';//支付超时时间

    /**
     * 获取授权引导连接
     * @return string
     */
    public function getOpenauthUrl($returnUrl)
    {
        $biz = [
            'response_type' => 'code',
            'client_id' => $this->client_id,
            'redirect_uri' => urlencode($returnUrl)
        ];
        return $this->gateway . '/#/oauth/authorize?' . http_build_query($biz);
    }

    /**
     * 获取用户信息
     * 生产环境https://open.blockcity.gxb.io/api/user/baseinfo
     * 测试环境https://sandbox.blockcity.gxb.io/openapi/user/baseinfo
     * @param $token string access_token
     * @param $url string 用户信息接口地址
     * @return mixed
     * @throws Exception
     */
    public function getUser($token, $url)
    {
        $biz = [
            'client_id' => $this->client_id,
            'method' => 'user.baseinfo',
            'access_token' => $token,
            'timestamp' => self::getMillisecond()
        ];
        $biz['sign'] = $this->requestSign($biz);
        $response = $this->curl($url, $biz);
        if ($response['code'] != 0) {
            throw new Exception($response['errorCode']);
        }
        return $response['data'];
    }

    /**
     * @param $biz string 业务参数
     * @param $notifyUrl string 通知地址
     * @return mixed
     */
    public function createPayOrder($biz,$notifyUrl)
    {
        $url = $this->gateway . '/api/blockpay/api/gateway';
        $requestParam = [
            'app_id' => $this->client_id,
            'method' => 'blockpay.trade.app.pay',
            'timestamp' => self::getMillisecond(),
            'version' => '1.0',
            'notify_url' => $notifyUrl,
            'biz_content' => $biz,
            'pay_expire' => $this->pay_expire
        ];
        $requestParam['sign'] = $this->rsaSign($biz . $requestParam['timestamp']);
        $response = $this->curl($url, $requestParam, true);
        if ($response['success'] !== true) {
            throw new Exception($response['errorCode']);
        }
        return $response['data'];
    }

    /**
     * 获取access_token
     * @param $authCode string
     * @return mixed
     * @throws Exception
     */
    public function getToken($authCode)
    {
        $biz = [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'code' => $authCode
        ];
        $response = $this->curl($this->auth_url, $biz);
        if ($response['success'] != true) {
            throw new Exception($response);
        }
        return $response['data'];
    }

    /**
     * 请求签名
     * @param $params array 请求参数
     * @return string
     */
    private function requestSign($params)
    {
        $string = '';
        $params['client_secret'] = $this->client_secret;
        ksort($params);
        foreach ($params as $k => $v) {
            $string .= $k . $v;
        }
        return md5($string);
    }

    /**
     * 签名
     * @param $data string
     * @return string
     */
    private function rsaSign($data)
    {
        $priKey = $priKey = file_get_contents($this->rsa_private_key_file);;
        $res = openssl_get_privatekey($priKey);
        openssl_sign($data, $sign, $res, OPENSSL_ALGO_SHA256);
        openssl_free_key($res);
        $sign = base64_encode($sign);
        return $sign;
    }

    /**
     * 验证签名是否通过
     * @param $content string
     * @return bool
     */
    public function checkRsaSign($content)
    {

        $content = json_decode($content, true);
        $sign = $content['sign'];
        //去掉签名后按ascii排序，以queryParam形式拼接字符串
        unset($content['sign']);
        ksort($content);
        $content = array_map(function ($v) {
            if (is_bool($v)) {
                return ($v) ? 'true' : 'false';
            }
            return $v;
        }, $content);
        $string = http_build_query($content);
        //将签名信息解密
        $decryptContent = $this->decrypt($sign);
        if ($string == $decryptContent) {
            return true;
        }
        return false;
    }

    /**
     * 使用私钥解密
     * @param $crypt string 密文
     * @return string
     */
    private function decrypt($crypt)
    {
        $priKey = file_get_contents($this->rsa_private_key_file);
        $res = openssl_get_privatekey($priKey);
        $result = '';
        $crypt = base64_decode($crypt);
        for ($i = 0; $i < strlen($crypt) / 256; $i++) {
            $data = substr($crypt, $i * 256, 256);
            openssl_private_decrypt($data, $decrypt, $res);
            $result .= $decrypt;
        }
        openssl_free_key($res);
        return $result;
    }

    /**
     * curl请求
     * @param $url string 地址
     * @param $requestParam mixed 请求参数
     * @param $isJson bool 是否为json格式
     * @return mixed
     */
    private function curl($url, $requestParam, $isJson = false)
    {
        $curl = new curl\Curl();
        if ($isJson) {
            $curl->setRequestBody(json_encode($requestParam))
                ->setHeaders([
                    'Content-Type' => 'application/json',
                    'Content-Length' => strlen(json_encode($requestParam))
                ]);
        } else {
            $curl->setOption(CURLOPT_POSTFIELDS, http_build_query($requestParam));
        }
        $response = $curl->post($url);
        $response = json_decode($response, true);
        return $response;
    }

    /**
     * 获取带毫秒时间戳
     */
    public static function getMillisecond() {
        list($t1, $t2) = explode(' ', microtime());
        return (float)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);
    }


}