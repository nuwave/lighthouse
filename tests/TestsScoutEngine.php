<?php declare(strict_types=1);

namespace Tests;

use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\NullEngine;
use Mockery\MockInterface;

trait TestsScoutEngine
{
    /** @var \Mockery\MockInterface&\Laravel\Scout\EngineManager */
    protected EngineManager $engineManager;

    /** @var \Mockery\MockInterface&\Laravel\Scout\Engines\NullEngine */
    protected NullEngine $engine;

    public function setUpScoutEngine(): void
    {
        $this->engineManager = \Mockery::mock(EngineManager::class);
        $this->app->singleton(EngineManager::class, fn (): MockInterface => $this->engineManager);

        $this->engine = \Mockery::mock(NullEngine::class)
            ->makePartial();

        $this->engineManager
            ->shouldReceive('engine')
            ->andReturn($this->engine);
    }
}
