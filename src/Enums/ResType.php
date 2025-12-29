<?php
namespace Oiapi\Enums;

enum ResType : string {
	case JSON = 'json';
	case STRING = 'string';
	case IMAGE = 'image';
	case AUDIO = 'audio';
	case XML = 'xml';
	case DEFAULT = 'default';
}