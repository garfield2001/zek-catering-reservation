<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'pgsql' || $app->configurationIsCached() || $app['config']->get('database.connections.pgsql.database') !== 'zek_catering_test' || (string) $app['config']->get('database.connections.pgsql.port') !== '5432' || $app['config']->get('database.connections.pgsql.host') !== '127.0.0.1') {
            throw new \RuntimeException('Tests require uncached configuration and zek_catering_test on PostgreSQL port 5432.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }
}
