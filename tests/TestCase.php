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

        $identity = $app['db']->selectOne('select current_database() as name, inet_server_port() as port');
        if ($identity->name !== 'zek_catering_test' || (int) $identity->port !== 5432) {
            throw new \RuntimeException('Tests refused an unexpected actual database connection.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }
}
