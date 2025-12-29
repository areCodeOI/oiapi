<?php
namespace Oiapi;

/**
 * @name Utils
 * @desc 工具类，一些可能会用到的功能
 */

class Utils {
	/**
	 * @access public
	 * @desc 优化原来的http_build_query，使用PHP_QUERY_RFC3986标准
	 * @param mixed $data
	 * @return string|false
	 */
	public static function http_build_query($data) {
		return http_build_query($data, '', '&', PHP_QUERY_RFC3986);
	}
	/**
	 * @access public
	 * @json|object 格式化输出
	 * @param array|object $array 需要输出的内容
	 * @return string
	 */
	public static function json(array|object $array) {
		return json_encode($array, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	}
	/**
	 * Cookie转json
	 * @access public
	 * @param array $Cookie 需要转换的Cookie
	 * @return array
	 */
	public static function Cookie2json($Cookie = '') {
		$e = [];
		foreach(explode(';', $Cookie) as $v) {
			if(preg_match('/(.+?)=(.+)/', $v, $preg)) {
				$e[trim($preg[1])] = $preg[2];
			}
		}
		return $e;
	}
	/**
	 * json转Cookie
	 * @access public
	 * @param array $json jsonCookie
	 * @return string
	 */
	public static function json2Cookie(array $json) {
		$Cookie = '';
		foreach($json as $k=>$v) {
			if(is_array($v)) {
				$Cookie .= "{$k}=". json_encode($v, 320) . '; ';
			} else {
				$Cookie .= "{$k}={$v}; ";
			}
		}
		return $Cookie;
		return trim($Cookie, '; ');
	}
	/**
	 * 检测内容是否是中文
	 * @access public
	 * @param string $str 需要检测的内容
	 * @return bool
	 */
	public static function isChinese(string $str): bool {
		return preg_match('/^[\x{4e00}-\x{9fa5}]+$/u', $str) ? true : false;
	}
}