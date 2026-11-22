<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Str;

beforeEach(function () {
    config([
        'app.debug' => false,
        'database.default' => 'sqlite',
        'database.connections.sqlite.url' => null,
        'database.connections.sqlite.database' => ':memory:',
    ]);
});

test('the health endpoint reports an available database', function () {
    $this->getJson('/up')->assertOk()->assertExactJson(['status' => 'up']);
});

test('the health endpoint reports a database outage without publishing connection details', function () {
    Exceptions::fake();
    config(['database.connections.sqlite.database' => sys_get_temp_dir().'/medloby-health-'.Str::uuid().'.sqlite']);

    $this->getJson('/up')->assertInternalServerError()->assertExactJson(['status' => 'down']);

    Exceptions::assertReported(QueryException::class);
});
