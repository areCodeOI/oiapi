<?php
namespace Oiapi;

use Oiapi\Enums\ReqType;
use Oiapi\Enums\ResType;
use Oiapi\Enums\Config;

/**
 * @name Client
 * @desc Curl HTTP请求工具类
 * @desc 封装CURL扩展，支持GET/POST/HEAD等请求方法，提供头部设置、代理、响应格式处理等功能
 * @desc 支持链式调用，优化内存占用和执行效率
 */
class Client {
	/**
	 * 默认请求头信息
	 * @var array 键值对形式的HTTP头部（如User-Agent）
	 */
	private const DEFAULT_HEADERS = [
		'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36'
	];

	/**
	 * 默认CURL配置选项
	 * @var array CURL常量为键的配置（如超时时间、SSL验证等）
	 */
	private const DEFAULT_OPT = [
		CURLOPT_CONNECTTIMEOUT => 10,	   // 连接超时时间（秒）
		CURLOPT_TIMEOUT => 30,			  // 总超时时间（秒）
		CURLOPT_SSL_VERIFYHOST => false,		// 不验证SSL主机
		CURLOPT_SSL_VERIFYPEER => false,	// 不验证SSL证书
		CURLOPT_RETURNTRANSFER => true,	 // 结果返回为字符串（而非直接输出）
		CURLOPT_HEADER => true,			 // 响应中包含头部信息
		CURLOPT_CUSTOMREQUEST => 'GET'	  // 默认请求方法
	];

	/**
	 * 支持的HTTP方法
	 * @var array 允许的请求方法列表
	 */
	private const ALLOWED_METHODS = ['GET', 'POST', 'HEAD', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'FILE'];

	/**
	 * 当前请求头
	 * @var array 键为头部名、值为头部内容的数组
	 */
	private $headers = self::DEFAULT_HEADERS;

	/**
	 * CURL配置选项
	 * @var array 实时调整的CURL选项，初始为默认配置
	 */
	private $setopt = self::DEFAULT_OPT;

	/**
	 * 请求结果主体
	 * @var mixed 按accept格式处理后的响应内容（字符串/数组/对象）
	 */
	private $result;

	/**
	 * CURL请求信息
	 * @var array curl_getinfo()返回的详细信息（如响应码、耗时等）
	 */
	private $info;

	/**
	 * 错误信息
	 * @var string|int 错误时为错误描述字符串，成功时为0
	 */
	private $error = 0;
	/**
	 * 错误状态码
	 * @var int 错误时为错误码，成功时为0
	 */
	private $status = 0;

	/**
	 * 响应结果格式
	 * @var string 支持'string'（默认）、'json'（数组）、'object'（对象）
	 */
	private ResType $accept = ResType::STRING;

	/**
	 * 扩展信息存储
	 * @var array 存储响应头、Cookie等额外信息
	 */
	private $opt = [];

	/**
	 * 请求URL
	 * @var string|null 待请求的接口地址
	 */
	private ?string $url = null;

	/**
	 * 请求参数
	 * @var mixed GET参数（字符串）或POST参数（字符串/数组）
	 */
	private mixed $data = null;
	
	/**
	 * 路由参数
	 * @var mixed GET参数（字符串）或POST参数（字符串/数组）
	 */
	public string $route = '';

	/**
	 * 请求方法
	 * @var string 大写的请求方法（如'GET'、'POST'、'HEAD'）
	 */
	private ReqType $method = ReqType::GET;

	/**
	 * 构造函数
	 * @param string|null $url 请求地址（可选）
	 * @param mixed $data 请求参数（可选，GET为字符串，POST为字符串/数组）
	 * @param string $method 请求方法（可选，默认'GET'）
	 */
	public function __construct(?string $url = '', mixed $data = null, ReqType $method = ReqType::GET)
	{
		// $this->accept = ResType::STRING;
		$this->go($method, $url, $data);

		// 初始化请求头（转换为CURL需要的格式）
		$this->initHeaders();
	}

	/**
	 * 静态初始化方法（推荐）
	 * 便于链式调用，如Curl::init()->get(...)
	 * @param string $url 请求地址
	 * @param mixed $data 请求参数（可选）
	 * @param string $method 请求方法（可选，默认'GET'）
	 * @return self 返回当前实例，支持链式调用
	 */
	public static function init(?string $url = null, mixed $data = null, string $method = 'GET'): self
	{
		return new static($url, $data, $method);
	}

	/**
	 * 执行CURL请求（核心方法）
	 * 处理请求发送、响应解析、信息存储
	 * @return self 返回当前实例，支持链式调用
	 */
	public function curl(): self
	{
		// 初始化CURL资源
		$curl = curl_init();
		
		// 应用所有配置选项
		curl_setopt_array($curl, $this->setopt);

		// 执行请求并获取原始响应（包含头部）
		$response = curl_exec($curl);
		// var_dump($response);
		// 获取响应头长度（用于分离头部和主体）
		$headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);

		// 分离响应头和主体
		$header = $headerSize ? substr($response, 0, $headerSize) : '';
		$body = $headerSize ? substr($response, $headerSize) : $response;

		// 按指定格式处理响应主体
		$this->result = match (strtolower($this->accept->value)) {
			'json' => json_decode($body, true),   // JSON转数组
			'object' => json_decode($body),	   // JSON转对象
			default => $body					  // 保留原始字符串
		};

		// 处理响应头和Cookie
		if ($header) {
			$this->opt['Header'] = $header;
			// 提取Set-Cookie并转换为JSON格式
			if (preg_match_all('/Set-Cookie: (.+?);/im', $header, $cookies)) {
				$this->opt['Cookie'] = Utils::cookie2json(join(';', $cookies[1]));
			} else {
				$this->opt['Cookie'] = [];
			}
		}

		// 存储请求信息和错误
		$this->info = curl_getinfo($curl);
		$this->status = curl_errno($curl);
		$this->error = $this->status ? curl_error($curl) : 0;

		// 释放CURL资源
		curl_close($curl);
		header('content-type: ' . $this->info['content_type']);
		return $this;
	}
	/**
	 * 原生CURL多句柄并发请求（适配批量回复请求）
	 * @param array $request 批量请求参数列表 [
	 *     'url' => url,
	 *     'method' => GET,
	 *     'body' => body,
	 *     'accept' => string,
	 * ]
	 * @return array 按请求顺序返回的响应结果
	 */
	public function multi(array $request): array
	{
		$multiHandle = curl_multi_init();
		$curlHandles = [];
		$resultMap = []; // 关联请求参数与响应结果
	
		// 1. 批量创建curl句柄，复用现有setopt配置
		foreach ($request as $key => $req) {
			$this->go(
				strToupper($req['method'] ?? 'GET'),
				$req['url'] ?? $this->url,
				$req['body'] ?? $this->data,
			);
			// 克隆当前curl实例的setopt，避免相互干扰
			$singleSetopt = $this->setopt;
			// $singleSetopt[CURLOPT_URL] = $req['url'];
	
			// 创建单条请求的curl句柄
			$ch = curl_init();
			curl_setopt_array($ch, $singleSetopt);
	
			// 关联句柄与请求参数，方便后续匹配结果
			$curlHandles[$key] = $ch;
			// $resultMap[$key] = $req;
	
			// 将句柄添加到多句柄中
			curl_multi_add_handle($multiHandle, $ch);
		}
	
		// 2. 执行并发请求，监控所有请求完成
		$running = null;
		do {
			// 处理请求状态，CURLM_CALL_MULTI_PERFORM表示需要继续处理
			curl_multi_exec($multiHandle, $running);
			// 让出CPU，避免阻塞，提升并发效率
			curl_multi_select($multiHandle);
		} while ($running > 0);
	
		// 3. 批量获取响应结果，映射回对应请求
		foreach ($curlHandles as $key => $ch) {
			// 获取当前请求的响应内容（模仿原有curl()方法的响应处理逻辑）
			$response = curl_multi_getcontent($ch);
			$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
			$body = $headerSize ? substr($response, $headerSize) : $response;
	
			// 复用原有accept格式处理响应（json/object/string）
			$resultMap[$key] = match (strtolower($request[$key]['accept'] ?? $this->accept)) {
				'json' => json_decode($body, true),
				'object' => json_decode($body),
				default => $body
			};
	
			// 移除句柄，释放资源
			curl_multi_remove_handle($multiHandle, $ch);
			curl_close($ch);
		}
	
		// 关闭多句柄
		curl_multi_close($multiHandle);
	
		// 返回按请求顺序排列的结果
		return array_values($resultMap);
	}

	/**
	 * 设置是否跟随重定向
	 * @param bool $bool 是否跟随（默认true）
	 * @return self 返回当前实例，支持链式调用
	 */
	public function location(bool $bool = true): self
	{
		$this->setopt[CURLOPT_FOLLOWLOCATION] = $bool;  // 跟随重定向
		$this->setopt[CURLOPT_AUTOREFERER] = $bool;	 // 自动设置Referer
		return $this;
	}

	/**
	 * 添加单个请求头
	 * @param string $key 头部名（如'Content-Type'）或完整头部（如'Content-Type: application/json'）
	 * @param string $value 头部内容（若$key为完整头部，此参数可留空）
	 * @return self 返回当前实例，支持链式调用
	 */
	public function addHeader(string $key, string $value = ''): self
	{
		// 若$key包含': '且无$value，视为完整头部（如'Host: example.com'）
		if (str_contains($key, ': ') && !$value) {
			[$k, $v] = explode(': ', $key, 2);
			$this->headers[$k] = $v;
		} else {
			$this->headers[$key] = $value;
		}
		// 重新初始化头部（同步到CURL选项）
		$this->initHeaders();
		return $this;
	}
	public function addHeaders(string $key, string $value = ''): self {
		return $this->addHeader($key, $value);
	}

	/**
	 * 批量设置请求头
	 * @param array $headers 头部数组（支持两种格式：[key=>value]或['key: value']）
	 * @return self 返回当前实例，支持链式调用
	 */
	public function setHeaders(array $headers): self
	{
		foreach ($headers as $k => $v) {
			// 若键为数字且值是完整头部（如'Content-Type: ...'），直接调用addHeader
			if (is_int($k) && str_contains($v, ': ')) {
				$this->addHeader($v);
			} else {
				// 否则按[key=>value]格式添加
				$this->addHeader($k, $v);
			}
		}
		return $this;
	}

	/**
	 * 初始化请求头（转换为CURL需要的格式）
	 * 将[$key=>$value]转换为['key: value']格式的数组
	 * @return void
	 */
	private function initHeaders(): void
	{
		$this->setopt[CURLOPT_HTTPHEADER] = array_map(
			fn($k, $v) => "{$k}: {$v}",  // 匿名函数：拼接头部字符串
			array_keys($this->headers),
			array_values($this->headers)
		);
	}

	/**
	 * URL编码（处理特殊字符）
	 * 对非ASCII字符进行URL编码（长度≥3的字符）
	 * @param string $url 原始URL
	 * @return string 编码后的URL
	 */
	private function encodeUrl(string $url): string
	{
		if (preg_match('/[\x7f-\xff]/', $url)) {
			return preg_replace_callback(
				'/([\x7f-\xff]+)/',
				fn($m) => strlen($m[0]) >= 3 ? urlencode($m[0]) : $m[0],
				$url
			);
		}
		return $url;
	}


	/**
	 * 设置响应结果格式
	 * @param string $accept 格式类型：'string'（默认）、'json'、'object'
	 * @return self 返回当前实例，支持链式调用
	 */
	public function accept(string $accept = 'string'): self
	{
		$this->accept = $accept;
		return $this;
	}

	/**
	 * 设置是否仅获取响应头（不获取主体）
	 * @param bool $bool true=仅头，false=完整响应（默认）
	 * @return self 返回当前实例，支持链式调用
	 */
	public function nobody(bool $bool): self
	{
		$this->setopt[CURLOPT_NOBODY] = $bool;
		return $this;
	}

	/**
	 * 设置请求超时时间
	 * @param int $time 超时时间（秒）
	 * @return self 返回当前实例，支持链式调用
	 */
	public function timeout(int $time): self
	{
		$this->setopt[CURLOPT_TIMEOUT] = $time;
		return $this;
	}

	/**
	 * 设置请求编码格式（如gzip压缩）
	 * @param string $encode 编码类型（默认'gzip'）
	 * @return self 返回当前实例，支持链式调用
	 */
	public function encode(string $encode = 'gzip'): self
	{
		$this->setopt[CURLOPT_ENCODING] = $encode;
		return $this;
	}

	/**
	 * 设置代理（或取消代理）
	 * @param string|bool $proxy 代理地址（如'127.0.0.1:8888'），false则取消代理
	 * @param string|null $config 代理认证信息（如'user:pass'，可选）
	 * @return self 返回当前实例，支持链式调用
	 */
	public function proxy(string|bool $proxy, ?string $config = null): self
	{
		if ($proxy === false) {
			// 取消代理配置
			unset(
				$this->setopt[CURLOPT_PROXY],
				$this->setopt[CURLOPT_PROXYUSERPWD],
				$this->setopt[CURLOPT_PROXYTYPE]
			);
			return $this;
		}

		// 设置代理地址
		$this->setopt[CURLOPT_PROXY] = $proxy;
		// 若有认证信息，设置代理用户名密码
		if ($config && str_contains($config, ':')) {
			$this->setopt[CURLOPT_PROXYUSERPWD] = $config;
		}
		// 设置代理类型为HTTP
		$this->setopt[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
		return $this;
	}

	/**
	 * 获取JSON格式响应（数组类型）
	 * @return array|null|bool 成功返回数组，失败返回null/false
	 */
	public function json(): array|null|bool
	{
		return json_decode($this->result, true);
	}
	
	/**
	 * 获取JSON格式响应（数组类型）
	 * @return array|null|bool 成功返回数组，失败返回null/false
	 */
	public function array(): array|null|bool
	{
		return $this->json();
	}

	/**
	 * 获取JSON格式响应（对象类型）
	 * @return object|null|bool 成功返回对象，失败返回null/false
	 */
	public function object(): object|null|bool
	{
		return json_decode($this->result);
	}

	/**
	 * 获取字符串格式响应
	 * @return string 响应主体的字符串形式
	 */
	public function string(): string
	{
		return (string)$this->result;
	}

	/**
	 * 重置实例状态（清除历史数据）
	 * 恢复默认配置，可重新发起新请求
	 * @return self 返回当前实例，支持链式调用
	 */
	public function clear(): self
	{
		$this->url = null;
		$this->data = null;
		$this->method = ReqType::GET;
		$this->headers = self::DEFAULT_HEADERS; // 恢复默认请求头
		$this->setopt = self::DEFAULT_OPT; // 恢复默认CURL配置
		$this->result = null; // 清空响应结果
		$this->info = null; // 清空请求信息
		$this->error = 0; // 重置错误状态
		$this->accept = ResType::STRING; // 恢复默认响应格式
		$this->opt = []; // 清空扩展信息
		$this->initHeaders(); // 重新初始化请求头（同步默认配置）
		return $this;
	}

	/**
	 * 构建multipart/form-data格式表单数据
	 * 用于文件上传或复杂表单提交
	 * @param array $param 表单参数（键为字段名，值为字段内容）
	 * @param string|null $delimiter 分隔符（默认自动生成）
	 * @return string 拼接后的multipart格式数据
	 */
	public function buildData(array $param, ?string $delimiter = null): string
	{
		$delimiter ??= '----' . uniqid(); // 使用更标准的分隔符格式
		$eol = "\r\n";
		$data = [];

		foreach ($param as $name => $content) {
			$data[] = "--{$delimiter}{$eol}";
			
			// 判断是否是文件（通过文件路径或资源）
			if (is_string($content) && file_exists($content)) {
				// 文件上传
				$filename = basename($content);
				$fileContent = Operate::get($content);
				$data[] = "Content-Disposition: form-data; name=\"{$name}\"; filename=\"{$filename}\"{$eol}";
				$data[] = "Content-Type: " . $this->getMimeType($content) . "{$eol}{$eol}";
				$data[] = $fileContent . $eol;
			} else if ($content instanceof \Imagick) {
				// 如果是Imagick
				$mime = strtolower($content->getImageFormat());
				$filename = uniqid() . '.' . $mime;
				$data[] = "Content-Disposition: form-data; name=\"{$name}\"; filename=\"{$filename}\"{$eol}";
				$data[] = "Content-Type: image/{$mime}{$eol}{$eol}";
			} else {
				// 普通字段
				$data[] = "Content-Disposition: form-data; name=\"{$name}\"{$eol}{$eol}";
				$data[] = $content . $eol;
			}
		}

		$data[] = "--{$delimiter}--{$eol}";
		return implode('', $data);
	}
	/**
	 * 根据文件扩展名获取MIME类型
	 * @param string $filename 文件名或路径
	 * @return string MIME类型
	 */
	private function getMimeType(string $filename): string
	{
		$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		
		$mimeTypes = [
			'png'  => 'image/png',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'gif'  => 'image/gif',
			'bmp'  => 'image/bmp',
			'pdf'  => 'application/pdf',
			'txt'  => 'text/plain',
		];
		
		return $mimeTypes[$extension] ?? 'application/octet-stream';
	}
	/**
	 * 获取请求URL
	 * @return string|null 当前请求的URL，未设置时返回null
	 */
	public function getUrl(): ?string
	{
		return $this->url;
	}
	
	/**
	 * 获取请求参数
	 * @return mixed 请求参数（GET为字符串，POST为字符串/数组），未设置时返回null
	 */
	public function getData(): mixed
	{
		return $this->data;
	}
	
	/**
	 * 获取请求方法
	 * @return string 当前请求方法（如'GET'、'POST'、'HEAD'）
	 */
	public function getMethod(): string
	{
		return $this->method;
	}
	
	/**
	 * 获取请求头信息
	 * @return array 键为头部名、值为头部内容的数组（默认包含User-Agent等）
	 */
	public function getHeaders(): array
	{
		return $this->headers;
	}
	
	/**
	 * 获取请求结果
	 * @return mixed 按accept格式处理后的响应内容（字符串/数组/对象），未请求时返回null
	 */
	public function getResult(): mixed
	{
		return $this->result;
	}

	/**
	 * 获取请求详细信息
	 * @return array|null curl_getinfo()返回的数组（如响应码、耗时、大小等）
	 */
	public function getInfo(): array|null
	{
		return $this->info;
	}

	/**
	 * 获取错误信息
	 * @return string|int 错误时返回错误描述字符串，成功时返回0
	 */
	public function getError(): string|int
	{
		return $this->error;
	}
	
	/**
	 * 动态获取私有变量值
	 * 支持通过属性名获取类内私有变量（如url、headers、result等）
	 * @param string $name 私有变量名
	 * @return mixed 对应变量的值，若变量不存在则返回null
	 */
	public function __get(string $name): mixed
	{
		// 判断变量是否存在于当前实例中
		if (property_exists($this, $name)) {
			return $this->$name;
		}
		if (method_exists($this, $name)) {
			return $this->$name();
		}
		return null;
	}
	
	/**
	 * 检查私有变量是否存在
	 * 配合__get使用，判断变量是否可获取
	 * @param string $name 私有变量名
	 * @return bool 存在返回true，否则返回false
	 */
	public function __isset(string $name): bool
	{
		return property_exists($this, $name);
	}

	/**
	 * 魔术方法：统一处理HTTP请求方法
	 * 支持GET、POST、HEAD、PUT、DELETE、PATCH、OPTIONS等方法
	 * @param string $name 方法名（如'get'、'post'）
	 * @param array $arguments 参数数组，第一个为URL，第二个为请求参数
	 * @return self 返回当前实例，支持链式调用
	 * @throws \BadMethodCallException 当方法名不支持时抛出异常
	 */
	public function __call(string $name, array $arguments): self
	{
		$method = strtoupper($name);
		
		// 检查是否为支持的HTTP方法
		if (!in_array($method, self::ALLOWED_METHODS)) {
			throw new \BadMethodCallException("不支持 {$name} 请求");
		}
		if(!$arguments && $this->url) return $this->curl();
		$url = $arguments[0] ?? ($this->url ? $this->url : '');
		$data = $arguments[1] ?? ($this->data ? $this->data : null);
		
		$this->go(ReqType::from($method), $url, $data);

		return $this->curl();
	}
	private function route(array $data) {
		$this->route = '/' . join('/', $data);
	}
	private function go($method, $url, $data) {
		$this->method = $method === 'FILE' ? ReqType::POST : $method;
		$this->url = Config::BASE_SCHEME->value . '://' . Config::BASE_URL->value . '/' . Config::BASE_PATH->value . '/' . $url;
		if($this->route) $this->url .= $this->route;
		$this->data = $data;
		// 设置URL
		$this->setopt[CURLOPT_URL] = $this->url;
		$this->setopt[CURLOPT_CUSTOMREQUEST] = $this->method->value;

		// 特殊处理POST类方法（包含请求体）
		if (in_array($method, ['POST', 'PUT', 'PATCH', 'FILE'])) {
			$this->setopt[CURLOPT_POST] = 1;
			if($method !== 'FILE') {
				if(is_array($data) || is_object($data)) $data = json_encode($data, 320);
				if(Utils::isJson($data)) $this->addheaders('content-type: application/json');
			}
			$this->setopt[CURLOPT_POSTFIELDS] = $data;
		} 
		// 特殊处理HEAD方法
		elseif ($method === 'HEAD') {
			$this->setopt[CURLOPT_NOBODY] = true;
			
			// 回调函数：收集响应头
			$this->setopt[CURLOPT_HEADERFUNCTION] = function ($curl, $line) {
				$this->opt['Header'] .= $line;
				return strlen($line);
			};

			// 回调函数：忽略响应主体
			$this->setopt[CURLOPT_WRITEFUNCTION] = function () {
				return 0;
			};
		}
		// GET、DELETE、OPTIONS等方法处理URL参数
		else {
			if ($data) {
				$this->url .= (str_contains($this->url, '?') ? '&' : '?') . (is_array($data) || is_object($data) ? http_build_query($data) : $data);
				$this->setopt[CURLOPT_URL] = $this->encodeUrl($this->url);
			}
		}
	}

	/**
	 * 魔术方法：转换为字符串时返回响应主体
	 * @return string 同string()方法的返回值
	 */
	public function __toString(): string
	{
		return $this->string();
	}
}