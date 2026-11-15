<?php

//chat-server.php

    use Ratchet\Server\IoServer;
	use Ratchet\Http\HttpServer;
	use Ratchet\WebSocket\WsServer;
	use MyApp\Chat;

	   require dirname(__DIR__) . 'vendor/autoload.php';
		//require plugin_path('vendor/autoload.php'); // Make sure this path is correct
		//$autoload_path = require_once('vendor/autoload.php');
		//echo $autoload_path; // Check if the path is correct
		//require $autoload_path;


	    $server = IoServer::factory(
	        new HttpServer(
	            new WsServer(
	                new Chat()
	            )
	        ),
	        8080
	    );

	    $server->run();