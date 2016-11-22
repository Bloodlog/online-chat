<?php
namespace App\auth;
use App\auth\Auth;
use \Memcache;
/*

*/
/**
* 
*/
class AuthMiddlewareSite extends Auth
{
	/*

	*/
	/*function __construct($id, $hash, $config){
		$this->config = $config;
		parent::__construct($id, $hash);
	}*/

	public function __invoke($request, $response, $next){
		//init
		$cache = new Memcache();
		$cache->addServer('127.0.0.1', 11211);
		$session = new \App\db\memcache\MemcachedSessionHandler($cache);
		session_start();
		if(isset($_SESSION['user'])){
		   //do something
			echo 'Есть в сессиях Алоало';
			print_r($cache->get('sess_'.session_id()));
		}else
			$_SESSION['user'] = 12345678910112;
		//echo $_SESSION['some_key'];
		$response = $next($request, $response);
		return $response;
		/*if($this->user_id){

		

			//init
			$cache = new Memcached();
			$cache->addServer('127.0.0.1', 11211);
			$session = new MemcachedSessionHandler($cache);
			session_start();
			 
			//use
			if(isset($_SESSION['some_key'])){
			   //do something
			}
			 
			$_SESSION['some_key'] = 1;
		}*/
		// $request = $request->withAttribute('foo', 'bar');
		//	$foo = $request->getAttribute('foo');
	}
}