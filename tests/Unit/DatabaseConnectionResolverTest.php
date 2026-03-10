<?php

use App\Support\DatabaseConnectionResolver;

it('keeps sqlsrv when a compatible driver is available', function () {
    expect(DatabaseConnectionResolver::resolve('sqlsrv', true, ['sqlite', 'sqlsrv']))
        ->toBe('sqlsrv');
});

it('falls back to sqlite when sqlsrv has no compatible driver', function () {
    expect(DatabaseConnectionResolver::resolve('sqlsrv', true, ['sqlite']))
        ->toBe('sqlite');
});

it('does not change non-sqlsrv connections', function () {
    expect(DatabaseConnectionResolver::resolve('mysql', true, ['sqlite']))
        ->toBe('mysql');
});
