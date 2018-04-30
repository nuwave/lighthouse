<?php
namespace Nuwave\Lighthouse\Support\WebSockets;

use Ratchet\MessageComponentInterface;
use Ratchet\WebSocket\WsServerInterface;
use Ratchet\ConnectionInterface;

class WebSocketServer implements MessageComponentInterface, WsServerInterface{
    

    /** The WebSocketController
     *
     * @Var \Nuwave\Lighthouse\Support\WebSockets\WebSocketController
     */
	protected $controller;

	public function __construct(WebSocketController $controller){
		$this->controller = $controller;
	}

    /**
     * @return void
     */
    public function onOpen(ConnectionInterface $conn){}

    /**
     * @return void
     */
    public function onMessage(ConnectionInterface $conn, $message)
    {
        $data = json_decode($message, true);
        switch ($data['type']) {
            case Protocol::GQL_CONNECTION_INIT:
                $controller->handleConnectionInit($conn, $data);
                break;
            case Protocol::GQL_START:
                $controller->handleStart($conn, $data);
                break;
            case Protocol::GQL_DATA:
                $controller->handleData($data);
                break;
            case Protocol::GQL_STOP:
                $controller->handleStop($conn, $data);
                break;
        }
    }

    /**
     * @return void
     */
    public function onClose(ConnectionInterface $conn){}

    /**
     * @return void
     */
    public function onError(ConnectionInterface $conn, \Exception $exception)
    {
    	\Log::error($exception);
    }

    public function getSubProtocols() : array
    {
        return ['graphql-ws'];
    }
}