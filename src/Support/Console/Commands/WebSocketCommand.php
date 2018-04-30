<?php

namespace Nuwave\Lighthouse\Support\Console\Commands;

use Illuminate\Console\Command;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\WebSocket\WsServerInterface;

class WebSocketCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lighthouse:websocket';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rub the WebSocket Subscriptions Server';

    /**
     * The WebSocket Server.
     *
     * @var Ratchet\WebSocket\WsServerInterface;
     */
    protected $server;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(WsServerInterface $server)
    {
    	$this->server = $server;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        \Log::info("Start Server");
        $server = IoServer::factory(
             new HttpServer(
                 new WsServer(
                     $this->server
                 )
             ),
             app('config')['broadcasting.connections.graphql.port']
        );
        $server->run();
    }
}
