<?php

namespace Tests\Support;

use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;

/**
 * Laravel's withoutVite() fake, plus fonts(): the framework fake inherits the
 * real fonts(), which reads the local build's font manifest and remembers
 * preloads across requests, so test output depended on whether `npm run build` ran.
 */
class NullVite extends Vite
{
    public function __invoke($entrypoints, $buildDirectory = null)
    {
        return new HtmlString('');
    }

    public function __call($method, $parameters)
    {
        return '';
    }

    public function __toString()
    {
        return '';
    }

    public function useIntegrityKey($key)
    {
        return $this;
    }

    public function useBuildDirectory($path)
    {
        return $this;
    }

    public function useHotFile($path)
    {
        return $this;
    }

    public function withEntryPoints($entryPoints)
    {
        return $this;
    }

    public function useScriptTagAttributes($attributes)
    {
        return $this;
    }

    public function useStyleTagAttributes($attributes)
    {
        return $this;
    }

    public function usePreloadTagAttributes($attributes)
    {
        return $this;
    }

    public function preloadedAssets()
    {
        return [];
    }

    public function reactRefresh()
    {
        return '';
    }

    public function content($asset, $buildDirectory = null)
    {
        return '';
    }

    public function asset($asset, $buildDirectory = null)
    {
        return '';
    }

    public function fonts($aliases = null)
    {
        return new HtmlString('');
    }
}
