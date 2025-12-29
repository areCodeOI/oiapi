<?php
namespace Oiapi;

class Manager {
	private static $instance = null;
	private Client $client;
	private function __construct() {
		$this->client = new Client();
	}
	public static function getInstance() {
		if(self::$instance === null) {
			self::$instance = new self;
		}
		return self::$instance;
	}
	public function getById(int $id) {
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
	public function getByName(string $name) {
		if($data = $this->search($name)) {
			foreach($data as $api) {
				if($api->ename === $name || $api->name === $name) return $api;
			}
		}
		throw new \Exception("接口 '{$name}' 不存在！");
	}
	public function search(string $name) {
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
	public function go(int | string $api, mixed $data = null, array $route = []) {
		if(is_int($api)) {
			$api = $this->getById($api)->ename;
		}
		if(Utils::isChinese($api)) {
			$api = $this->getByName($api)->ename;
		}
		$this->client->clear();
		$method = 'get';
		if($data) $method = 'post';
		if($route) $this->client->route($route);
		return match($method) {
			'post' => $this->client->post($api, $data)->result,
			default => $this->client->get($api)->result
		};
	}
}