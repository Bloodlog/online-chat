<?php
namespace Server;

use \Memcache;
use \ArrayObject;
/*
 *
 */
class Server{
	// Кеш
	private  $memcache;
	// Массив настроек
	private $settings;
	// Массив подключений
	public $connects;

	public function __construct($settings){
		$this->settings = new ArrayObject();
		$this->settings = $settings['settings'];
	}

	public function run()
	{
		$memcache = new Memcache();
		$memcache->addServer($this->settings['memcache']['host'], $this->settings['memcache']['port']);
		$this->memcache = $memcache;

		// создает мастер сокет и возвращает дескриптор (ресурс) сокета
		$this->socket = stream_socket_server($this->settings['socket']['host'] . ":" . $this->settings['socket']['port'], $errno, $errstr);
		if (!$this->socket) {
			die("$errstr ($errno)\n");
		}
		// pdo

		$this->loop();
	}

	public function loop(){
		while (true) {
			//формируем массив прослушиваемых сокетов:
			$read = $this->connects;
			$read[] = $this->socket;
			$write = $except = null;

			if (!stream_select($read, $write, $except, null)) {//ожидаем сокеты доступные для чтения (без таймаута)
				echo 'ожидаем сокеты доступные для чтения (без таймаута)';
				break;
			}

			if (in_array($this->socket, $read)) {//есть новое соединение
				//принимаем новое соединение и производим рукопожатие:
				if (($connect = stream_socket_accept($this->socket, -1)) && $info = $this->handshake($connect)) {
					$this->connects[] = $connect;//добавляем его в список необходимых для обработки
					$this->onOpen($connect, $info);//вызываем пользовательский сценарий
				}
				unset($read[ array_search($this->socket, $read) ]);
			}

			foreach($read as $connect) {//обрабатываем все соединения
				$data = fread($connect, 100000);

				if (!$data) { //соединение было закрыто
					fclose($connect);
					unset($this->connects[ array_search($connect, $this->connects) ]);
					$this->onClose($connect);//вызываем пользовательский сценарий
					continue;
				}

				$this->onMessage($connect, $data);//вызываем пользовательский сценарий
			}
		}

		//fclose($server);
	}

	function handshake($connect) {
		$info = array();

		$line = fgets($connect);
		$header = explode(' ', $line);
		$info['method'] = $header[0];
		$info['get'] = $header[1];

		//считываем заголовки из соединения
		while ($line = rtrim(fgets($connect))) {
			if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
				$info[$matches[1]] = $matches[2];
			} else {
				echo 'считываем заголовки из соединения';
				break;
			}
		}

		$address = explode(':', stream_socket_get_name($connect, true)); //получаем адрес клиента
		$info['ip'] = $address[0];
		$info['port'] = $address[1];

		if (empty($info['Sec-WebSocket-Key'])) {
			echo 'empty Sec-WebSocket-Key';
			return false;
		}

		//отправляем заголовок согласно протоколу вебсокета
		$SecWebSocketAccept = base64_encode(pack('H*', sha1($info['Sec-WebSocket-Key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
		$upgrade = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
			"Upgrade: websocket\r\n" .
			"Connection: Upgrade\r\n" .
			"Sec-WebSocket-Accept:$SecWebSocketAccept\r\n\r\n";
		fwrite($connect, $upgrade);

		return $info;
	}

	public function encode($payload, $type = 'text', $masked = false)
	{
		$frameHead = array();
		$payloadLength = strlen($payload);

		switch ($type) {
			case 'text':
				// first byte indicates FIN, Text-Frame (10000001):
				$frameHead[0] = 129;
				break;

			case 'close':
				// first byte indicates FIN, Close Frame(10001000):
				$frameHead[0] = 136;
				break;

			case 'ping':
				// first byte indicates FIN, Ping frame (10001001):
				$frameHead[0] = 137;
				break;

			case 'pong':
				// first byte indicates FIN, Pong frame (10001010):
				$frameHead[0] = 138;
				break;
		}

		// set mask and payload length (using 1, 3 or 9 bytes)
		if ($payloadLength > 65535) {
			$payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
			$frameHead[1] = ($masked === true) ? 255 : 127;
			for ($i = 0; $i < 8; $i++) {
				$frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
			}
			// most significant bit MUST be 0
			if ($frameHead[2] > 127) {
				return array('type' => '', 'payload' => '', 'error' => 'frame too large (1004)');
			}
		} elseif ($payloadLength > 125) {
			$payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
			$frameHead[1] = ($masked === true) ? 254 : 126;
			$frameHead[2] = bindec($payloadLengthBin[0]);
			$frameHead[3] = bindec($payloadLengthBin[1]);
		} else {
			$frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
		}

		// convert frame-head to string:
		foreach (array_keys($frameHead) as $i) {
			$frameHead[$i] = chr($frameHead[$i]);
		}
		if ($masked === true) {
			// generate a random mask:
			$mask = array();
			for ($i = 0; $i < 4; $i++) {
				$mask[$i] = chr(rand(0, 255));
			}

			$frameHead = array_merge($frameHead, $mask);
		}
		$frame = implode('', $frameHead);

		// append payload to frame:
		for ($i = 0; $i < $payloadLength; $i++) {
			$frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
		}

		return $frame;
	}

	function decode($data)
	{
		$unmaskedPayload = '';
		$decodedData = array();

		// estimate frame type:
		$firstByteBinary = sprintf('%08b', ord($data[0]));
		$secondByteBinary = sprintf('%08b', ord($data[1]));
		$opcode = bindec(substr($firstByteBinary, 4, 4));
		$isMasked = ($secondByteBinary[0] == '1') ? true : false;
		$payloadLength = ord($data[1]) & 127;

		// unmasked frame is received:
		if (!$isMasked) {
			return array('type' => '', 'payload' => '', 'error' => 'protocol error (1002)');
		}

		switch ($opcode) {
			// text frame:
			case 1:
				$decodedData['type'] = 'text';
				break;

			case 2:
				$decodedData['type'] = 'binary';
				break;

			// connection close frame:
			case 8:
				$decodedData['type'] = 'close';
				break;

			// ping frame:
			case 9:
				$decodedData['type'] = 'ping';
				break;

			// pong frame:
			case 10:
				$decodedData['type'] = 'pong';
				break;

			default:
				return array('type' => '', 'payload' => '', 'error' => 'unknown opcode (1003)');
		}

		if ($payloadLength === 126) {
			$mask = substr($data, 4, 4);
			$payloadOffset = 8;
			$dataLength = bindec(sprintf('%08b', ord($data[2])) . sprintf('%08b', ord($data[3]))) + $payloadOffset;
		} elseif ($payloadLength === 127) {
			$mask = substr($data, 10, 4);
			$payloadOffset = 14;
			$tmp = '';
			for ($i = 0; $i < 8; $i++) {
				$tmp .= sprintf('%08b', ord($data[$i + 2]));
			}
			$dataLength = bindec($tmp) + $payloadOffset;
			unset($tmp);
		} else {
			$mask = substr($data, 2, 4);
			$payloadOffset = 6;
			$dataLength = $payloadLength + $payloadOffset;
		}

		/**
		 * We have to check for large frames here. socket_recv cuts at 1024 bytes
		 * so if websocket-frame is > 1024 bytes we have to wait until whole
		 * data is transferd.
		 */
		if (strlen($data) < $dataLength) {
			return false;
		}

		if ($isMasked) {
			for ($i = $payloadOffset; $i < $dataLength; $i++) {
				$j = $i - $payloadOffset;
				if (isset($data[$i])) {
					$unmaskedPayload .= $data[$i] ^ $mask[$j % 4];
				}
			}
			$decodedData['payload'] = $unmaskedPayload;
		} else {
			$payloadOffset = $payloadOffset - 4;
			$decodedData['payload'] = substr($data, $payloadOffset);
		}

		return $decodedData;
	}

	//пользовательские сценарии:
	//При открытии соединения
	public function onOpen($connect, $info) {
		echo "Open Socket: client ip " . $info['ip'] . " : " . $info['port'] . "\n";
		//parse get-query
		$sessionid = substr($info['get'], 1);
		//print_r($this->memcache->get('sess_'.$sessionid));

		echo 'Зарегистрирована сессия: ' . $sessionid . ' \n ';
		$jsonData = [
			'type'    => 'start',
			'message' => 'Соединение установлено',
			'name'    => 'МАкс',
			'image'   => 'img',
		];
		fwrite($connect, $this->encode(json_encode($jsonData)));
	}

	public function onClose($connect) {
		echo "Закрытие соединения\n ";
		//unset($_SESSION['count']);
		fclose($connect);
		return false;
	}
// При получении сообщения
	public function onMessage($connect, $data) {
		//$session = session_id(decode($data));

		//$test = $encodeData['payload'];
		//echo decode($data);
		/*echo json_decode($encodeData['payload']);
        if(isset($encodeData['PHPSESSID'])){
            $session = session_id($encodeData['PHPSESSID']);
            echo 'This is some_key: ' . $_SESSION['some_key'];
        }*/

		echo 'sendMessage\n';
		$encodeData = $this->decode($data);
		$jsonData = json_decode($encodeData['payload']);
		print_r($jsonData);
		$action = $jsonData->type;
		switch($action){
			case 'getAllMessage':{
				echo 'Получить все сообщения хочет клиент';
				break;
			}
			case 'message':{

				echo 'Сообщение прислал нам клиент';
				print_r($jsonData);
				$this->sendMessage($connect, $jsonData->message);
				break;
			}
			case 'logout':{

			}
		}
		//print_r($test);

		//echo "\n" . decode($data)['payload'] . "\n";
		//Отправить сообщение
		//fwrite($connect, json_encode($data));
	}
	public function sendMessage($connect, $message){
		echo 'Отправляем сообщение';
		$jsonData = [
			"type" => "addMessage",
			"message" => $message,
		];
		fwrite($connect, $this->encode(json_encode($jsonData)));
	}

}