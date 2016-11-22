<?php 
namespace app\db\memcache;
/*
*
*/
class MemcachedSessionHandler implements \SessionHandlerInterface
{
    /**
     * @var Memcached
     */
    private $memcached;
    private $ttl;
    private $prefix;
 
    public function __construct(
        \Memcache $memcached, 
        $expiretime = 86400, 
        $prefix = 'sess_')
    {
        $this->memcached = $memcached;
        $this->ttl = $expiretime;
        $this->prefix = $prefix;
        $this->useMe();
    }
 
    public function open($savePath, $sessionName)
    {
        return true;
    }
 
    public function close()
    {
        return true;
    }
 
    public function read($sessionId)
    {
        return $this->memcached->get($this->prefix . $sessionId) ? : '';
    }
 
    public function write($sessionId, $data)
    {
        return $this->memcached->set(
          $this->prefix . $sessionId, 
          $data, 
          0,
          time() + $this->ttl);
    }
 
    public function destroy($sessionId)
    {
        return $this->memcached->delete($this->prefix . $sessionId);
    }
 
    public function gc($lifetime)
    {
        return true;
    }
 
    private function useMe()
    {
        session_set_save_handler(
            array($this, 'open'),
            array($this, 'close'),
            array($this, 'read'),
            array($this, 'write'),
            array($this, 'destroy'),
            array($this, 'gc')
        );
 
        register_shutdown_function('session_write_close');
    }
}