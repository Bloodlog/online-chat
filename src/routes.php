<?php
// Routes

$app->get('/', function ($request, $response, $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");
    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->get('/login/', function ($request, $response, $args) use($app){
    // Sample log message

    $this->logger->info("Slim-Skeleton '/login/' route");
    //var_dump($request->getParsedBody());
    //var_dump($request);
    if($request->isGet()){
    	//var_dump($request->getQueryParams());
    	$requestVkAuth = $request->getQueryParams();
		$container = $app->getContainer();
		$vkSettings = $container->get('settings')['vk'];
		$hash = $vkSettings['app_id'] . $requestVkAuth['uid'] . $vkSettings['client_secret'];
		if (md5($hash) === $requestVkAuth['hash']) {

			$_SESSION['uid'] = $requestVkAuth['uid'];
			$_SESSION['hash'] = $requestVkAuth['hash'];
			echo "Авторизовался!";
			/*$requestVkAuth['first_name'];
			$requestVkAuth['last_name'];
			$requestVkAuth['photo'];*/
			$indexPath = rtrim(str_ireplace('index.php', '', $request->getUri()->getBasePath()), '/');
			$basePath = $request->getUri()->getHost() . $indexPath;
			//Перенаправляем пользователя на чат
			//Костыль на костыле
			$uri = 'http://' . $basePath . DIRECTORY_SEPARATOR . 'chat';
			var_dump($uri);
			//var_dump($app->getUri()->getBasePath());
			return $response->withRedirect((string)$uri, 200);
		}
    }
})->setName('login');

$app->get('/chat', function ($request, $response, $args){
	return $this->renderer->render($response, 'chat.phtml', $args);
})->setName('chat');


$app->get('/logout/', function ($request, $response, $args){
			unset($_SESSION['uid']);
			unset($_SESSION['hash']);
});