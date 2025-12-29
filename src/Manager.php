<?php
namespace Oiapi;

class Manager {
	private static $instance = null;
	private Client $client;
	private function __construct() {
		$this->client = new Client();
	}
	public static function getInstance() : self {
		if(self::$instance === null) {
			self::$instance = new self;
		}
		return self::$instance;
	}
	/**
	 * @param int $id        api ID
	 * @return object
	 */
	public function getById(int $id) : object {
		$this->client->get("getApiInfo?id={$id}");
		if($res = $this->client->object()) {
			switch($res->code) {
				case -301:
					throw new \Exception('该接口处于维护状态，请换个接口。');
					break;
				case 1:
					return $res->data->info;
				default:
					throw new \Exception($res->message);
			}
		}
		throw new \Exception("接口 ID '{$id}' 不存在，请检查 ID 是否合理！");
	}
	/**
	 * @param string $name        api名字
	 * @return object
	 */
	public function getByName(string $name) : object {
		if($data = $this->search($name)) {
			foreach($data as $api) {
				if($api->ename === $name || $api->name === $name) return $api;
			}
		}
		throw new \Exception("接口 '{$name}' 不存在！");
	}
	/**
	 * @param string $name        api名字
	 * @return object
	 */
	public function search(string $name) : object {
		$this->client->get("/ApiSearch?keyword=" . urlencode($name));
		if($res = $this->client->object()) {
			switch($res->code) {
				case 1:
					return $res->data;
					break;
				default:
					throw new \Exception($res->message);
			}
		}
		throw new \Exception('接口数据获取失败！');
	}
	/**
	 * @param int | string $api    访问的api，可以是id与名称
	 * @param mixed $data        请求数据，使用POST时填写数据
	 * @param array $route        路由参数[a, b, c]
	 * @return mixed
	 */
	public function go(int | string $api, mixed $data = null, array $route = []) : mixed {
		if(is_int($api)) {
			$api = $this->getById($api)->ename;
		}
		if(Utils::isChinese($api)) {
			$api = $this->getByName($api)->ename;
		}
		$this->client->clear();
		$method = 'get';
		if($data) {
			$method = 'post';
			$this->header($data);
		}
		if($route) $this->client->route($route);
		return match($method) {
			'post' => $this->client->post($api, $data)->result,
			default => $this->client->get($api)->result
		};
	}
	private function header(mixed $data) : void {
		$type = '';
		switch(gettype($data)) {
			case 'array':
			case 'object':
				$this->client->addheaders('Content-Type: application/json');
				break;
			// case null:
			default:
		}
	}
}