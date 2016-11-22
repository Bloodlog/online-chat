<?php
namespace server;
use app\Auth;
/*

*/
/**
* 
*/
class AuthStream extends Auth
{
	public $sessionid;

	public function userIdentity($data='')
	{
		$this->sessionid = $data->PHPSESSID;
		$cache->get('sess_'.$this->sessionid);
	}
}