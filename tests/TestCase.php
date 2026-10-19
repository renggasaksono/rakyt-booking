<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Vite as ViteFacade;
use Tests\Support\NullVite;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function withoutVite()
    {
        if ($this->originalVite == null) {
            $this->originalVite = app(Vite::class);
        }

        ViteFacade::clearResolvedInstance();

        $this->swap(Vite::class, new NullVite);

        return $this;
    }
}
