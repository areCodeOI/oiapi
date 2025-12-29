<?php
namespace Oiapi\Enums;

enum ReqType : string {
	case POST = 'POST';
	case GET = 'GET';
	case HEAD = 'HEAD';
	case PUT = 'PUT';
	case DELETE = 'DELETE';
	case PATCH = 'PATCH';
	case OPTIONS = 'OPTIONS';
}