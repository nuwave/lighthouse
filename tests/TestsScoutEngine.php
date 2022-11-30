<?php

namespace Tests;

use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\NullEngine;
use Mockery\MockInterface;

trait TestsScoutEngine
{
    /**
     * @var \Mockery\MockInterface&\Laravel\Scout\EngineManager
     */
    protected $engineManager;

    /**
     * @var \Mockery\MockInterface&\Laravel\Scout\Engines\NullEngine
     */
    protected $engine;

    public function setUpScoutEngine(): void
    {
        $this->engineManager = \Mockery::mock(EngineManager::class);
        $this->app->singleton(EngineManager::class, function (): MockInterface {
            return $this->engineManager;
        });

        $this->engine = \Mockery::mock(NullEngine::class)
            ->makePartial();

        $this->engineManager
            ->shouldReceive('engine')
            ->andReturn($this->engine);
    }
}
