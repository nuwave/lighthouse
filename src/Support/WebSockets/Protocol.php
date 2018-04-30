<?php
namespace Nuwave\Lighthouse\Support\WebSockets;

class Protocol{
	
	/**
	 * Protocol messages.
	 *
	 * @see https://github.com/apollographql/subscriptions-transport-ws/blob/master/src/message-types.ts
	 */
	const GQL_CONNECTION_INIT = 'connection_init'; // Client -> Server
	const GQL_CONNECTION_ACK = 'connection_ack'; // Server -> Client
	const GQL_CONNECTION_ERROR = 'connection_error'; // Server -> Client
	const GQL_CONNECTION_KEEP_ALIVE = 'ka'; // Server -> Client
	const GQL_CONNECTION_TERMINATE = 'connection_terminate'; // Client -> Server
	const GQL_START = 'start'; // Client -> Server
	const GQL_DATA = 'data'; // Server -> Client
	const GQL_ERROR = 'error'; // Server -> Client
	const GQL_COMPLETE = 'complete'; // Server -> Client
	const GQL_STOP = 'stop'; // Client -> Server
}