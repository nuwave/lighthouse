<?php
namespace Nuwave\Lighthouse\Support\WebSockets;

use Ratchet\MessageComponentInterface;
use Ratchet\WebSocket\WsServerInterface;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use Ratchet\ConnectionInterface;
use Firebase\JWT\JWT;
use GuzzleHttp\Psr7\ServerRequest;
use Nuwave\Lighthouse\Schema\Context;
use Illuminate\Contracts\Auth\UserProvider;
use ReflectionClass;

class WebSocketController{

    /**
     * The Resource Server instance.
     *
     * @var \League\OAuth2\Server\ResourceServer
     */
    protected $resourceServer;

    /**
     * The Passport Token Repository
     *
	 * @var Laravel\Passport\TokenRepository
	 */
    protected $tokenRepository;

    /**
     * The Laravel Auth User Provider
     *
     * @var \Illuminate\Contracts\Auth\UserProvider
     */
    protected $userProvider;

    /**
     * Subscription Storage 
     *
     * @var array
     */
    protected $subscriptions;

    /**
     * Connection Storage
     *
     * @var \SplObjectStorage
     */
    protected $connStorage;

    public function __construct($resourceServer = null, $tokenRepository = null, UserProvider $userProvider){
    	$this->subscriptions = [];
    	$this->connStorage = new \SplObjectStorage();
    	$this->resourceServer = $resourceServer;
    	$this->tokenRepository = $tokenRepository;
    	$this->userProvider = $userProvider;

    	graphql()->prepSchema();
    }

        /**
     * @param ConnectionInterface $conn
     *
     * @return void
     */
    public function handleConnectionInit(ConnectionInterface $conn, array $data){
        try {
        	$payload = array_get($data, 'payload');
  			$psr = new ServerRequest("ws", "", $payload, null, '1.1', []);

			if ($psr->hasHeader('authorization') && $this->resourceServer != null){
	            $psr = $this->resourceServer->validateAuthenticatedRequest($psr);
	            $auth = $psr->getHeader('authorization');
			}
			
            $this->connStorage->offsetSet($conn, ['auth' => $auth]);
            $response = [
                'type'    => WebSocketController::GQL_CONNECTION_ACK,
                'payload' => [],
            ];

            $conn->send(json_encode($response));
        } catch (\Exception $e) {
            $response = [
                'type'    => WebSocketController::GQL_CONNECTION_ERROR,
                'payload' => $e->getMessage(),
            ];
            //\Log::info($response);
            $conn->send(json_encode($response));
            $conn->close();
        }
    }

    /**
     * @param ConnectionInterface $conn
     * @param array               $data
     *
     * @return void
     */
    public function handleStart(ConnectionInterface $conn, array $data)
    {
        try {
            $payload = array_get($data, 'payload');
            $query = array_get($payload, 'query');
            if (is_null($query)) {
                throw new \Exception('Missing query parameter from payload');
            }
            $variables = array_get($payload, 'variables');
            $document = Parser::parse($query);
            /** @psalm-suppress NoInterfaceProperties */
            $operation = $document->definitions[0]->operation;
            if ($operation == 'subscription') {
                $data['name'] = $this->getSubscriptionName($document);
                $data['conn'] = $conn;

                //\Log::info("New Subscription: " . $data['name'] . "");

                $this->subscriptions[$data['name']][] = $data;
                end($this->subscriptions[$data['name']]);
                $data['index'] = key($this->subscriptions[$data['name']]);

                $connData = $this->connStorage->offsetExists($conn) ?
                	$this->connStorage->offsetGet($conn) : [];

                $connData['subscriptions'][$data['id']] = $data;

                $this->connStorage->offsetSet($conn, $connData);
            } else {

            	$connData = $this->connStorage->offsetExists($conn) ? $this->connStorage->offsetGet($conn) : [];
            	$user = $this->getUser($connData['auth']);
                $this->authUser($user);

            	$result = graphql()->execute(
			        $query,
			        new Context(null, $user),
			        $variables
			    );

		        $response = [
		            'type'    => WebSocketController::GQL_DATA,
		            'id'      => $data['id'],
		            'payload' => "test",
		        ];

		        $conn->send(json_encode($response));

                $response = [
                    'type' => WebSocketController::GQL_COMPLETE,
                    'id'   => $data['id'],
                ];
                $conn->send(json_encode($response));
            }
        } catch (\Exception $e) {
            $response = [
                'type'    => WebSocketController::GQL_ERROR,
                'id'      => $data['id'],
                'payload' => $e->getMessage(),
            ];
            \Log::error($e);
            $conn->send(json_encode($response));
            $response = [
                'type' => WebSocketController::GQL_COMPLETE,
                'id'   => $data['id'],
            ];
            // \Log::info($response);
            $conn->send(json_encode($response));
        }
    }

    /**
     * @return void
     */
   public function handleData(array $data)
    {
        $subscriptionName = $data['subscription'];
        $payload = $data['payload'];
        $class = $payload['class'];
        $params = $payload['params'];

        $constructor = (new ReflectionClass($class))->getConstructor();  
        $parameters = $constructor->getParameters();

        $args = [];

        foreach ($parameters as $parameter) {
            $args[$parameter->getPosition()] = $params[$parameter->getName()];
        }

        $event = new $class(...$args);
        $event->wakeup();

        // \Log::info('Event Fired: ' . $subscriptionName);

        $subscriptions = array_get($this->subscriptions, $subscriptionName);
        if (is_null($subscriptions)) {
            return;
        }
        foreach ($subscriptions as $subscription) {
            try{
                $query = array_get($subscription['payload'], 'query');
                $variables = array_get($subscription['payload'], 'variables');

                $conn = $subscription['conn'];

                $connData = $this->connStorage->offsetExists($conn) ? $this->connStorage->offsetGet($conn) : [];

                $user = $this->getUser($connData['auth']);
                $this->authUser($user);
                
                $result = graphql()->execute(
                    $query,
                    new Context(null, $user, $event),
                    $variables
                );

                $response = [
                    'type'    => WebSocketController::GQL_DATA,
                    'id'      => $subscription['id'],
                    'payload' => $result,
                ];
                $conn->send(json_encode($response));

            } catch (\Exception $e) {
                $response = [
                    'type'    => WebSocketController::GQL_ERROR,
                    'id'      => $subscription['id'],
                    'payload' => $e->getMessage(),
                ];
                \Log::error($e);
                $subscription['conn']->send(json_encode($response));
            }
        }
    }

    /**
     * @return void
     */
    public function handleStop(ConnectionInterface $conn, array $data)
    {
        $connSubscriptions = $this->connStorage->offsetGet($conn);
        $subscription = array_get($connSubscriptions, $data['id']);
        if (!is_null($subscription)) {
            unset($this->subscriptions[$subscription['name']][$subscription['index']]);
            unset($connSubscriptions[$subscription['id']]);
            $this->connStorage->offsetSet($conn, $connSubscriptions);
        }
    }

    public function getUser($authHeader){
        $psr = new ServerRequest("ws", "", $authHeader != null ? ['authorization' => $authHeader] : [], null, '1.1', []);

        if ($psr->hasHeader('authorization') && $this->resourceServer != null && $this->tokenRepository != null){
            $psr = $this->resourceServer->validateAuthenticatedRequest($psr);
            $auth = $psr->getAttributes();

            $token = $this->tokenRepository->find($auth['oauth_access_token_id']);
            $user = $this->userProvider->retrieveById($auth['oauth_user_id'])->withAccessToken($token);
        }

        return $user;
    }

    public function authUser($user){
        if (app('auth')->user() != $user){
            if ($user == null) app('auth')->logout();
            else app('auth')->setUser($user);
        }
    }

    /**
     * @param DocumentNode $document
     *
     * @return string
     */
    public function getSubscriptionName(DocumentNode $document) : string
    {
        /** @psalm-suppress NoInterfaceProperties */
        return $document->definitions[0]
            ->selectionSet
            ->selections[0]
            ->name
            ->value;
    }

    public function getSubscriptions() : array
    {
        return $this->subscriptions;
    }

    public function getConnStorage() : \SplObjectStorage
    {
        return $this->connStorage;
    }
}