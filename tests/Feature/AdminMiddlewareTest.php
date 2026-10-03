<?php

use App\Http\Middleware\AdminMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

test('non admin user is rejected by admin middleware', function () {
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    $request = Request::create(
        '/api/admin/test',
        'GET'
    );

    $request->setUserResolver(
        fn () => $user
    );

    $middleware = new AdminMiddleware();

    $response = $middleware->handle(
        $request,
        fn ($request) => response()->json([
            'success' => true,
        ])
    );

    expect($response->getStatusCode())
        ->toBe(Response::HTTP_FORBIDDEN);

    expect($response->getData(true))
        ->toMatchArray([
            'success' => false,
            'message' => 'Bu işlem için admin yetkisi gereklidir.',
        ]);
});

test('admin user is allowed by admin middleware', function () {
    $user = User::factory()->create([
        'is_admin' => true,
    ]);

    $request = Request::create(
        '/api/admin/test',
        'GET'
    );

    $request->setUserResolver(
        fn () => $user
    );

    $middleware = new AdminMiddleware();

    $response = $middleware->handle(
        $request,
        fn ($request) => response()->json([
            'success' => true,
        ])
    );

    expect($response->getStatusCode())
        ->toBe(Response::HTTP_OK);

    expect($response->getData(true))
        ->toMatchArray([
            'success' => true,
        ]);
});

test('guest user is rejected by admin middleware', function () {
    $request = Request::create(
        '/api/admin/test',
        'GET'
    );

    $request->setUserResolver(
        fn () => null
    );

    $middleware = new AdminMiddleware();

    $response = $middleware->handle(
        $request,
        fn ($request) => response()->json([
            'success' => true,
        ])
    );

    expect($response->getStatusCode())
        ->toBe(Response::HTTP_FORBIDDEN);

    expect($response->getData(true))
        ->toMatchArray([
            'success' => false,
            'message' => 'Bu işlem için admin yetkisi gereklidir.',
        ]);
});