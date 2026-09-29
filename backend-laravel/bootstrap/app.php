<?php

use Illuminate\Foundation\Application;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn (Request $request) => null);

        $middleware->api(append: [
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (QueryException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $requestId = (string) Str::uuid();
            $previous = $exception->getPrevious();
            $sqlState = (string) ($previous?->getCode() ?? $exception->getCode());
            $nativeErrorCode = $previous instanceof \PDOException
                ? (string) ($previous->errorInfo[1] ?? '')
                : '';
            $connectionFailure = ($previous instanceof \PDOException)
                && (str_starts_with($sqlState, '08')
                    || in_array($sqlState, ['1042', '2002', '2003', '2005', '2013'], true)
                    || in_array($nativeErrorCode, ['1042', '2002', '2003', '2005', '2013'], true));

            Log::error('API database operation failed.', [
                'request_id' => $requestId,
                'route_name' => $request->route()?->getName() ?? $request->path(),
                'connection_name' => $exception->connectionName,
                'exception_class' => $exception::class,
                'sql_state' => $sqlState,
                'native_error_code' => $nativeErrorCode,
                'connection_failure' => $connectionFailure,
            ]);

            if (! $connectionFailure) {
                return response()->json([
                    'message' => 'The request could not be completed.',
                    'request_id' => $requestId,
                ], 500)->header('X-Request-ID', $requestId);
            }

            return response()->json([
                'message' => 'Database unreachable.',
                'request_id' => $requestId,
            ], 503)->header('X-Request-ID', $requestId);
        });

        $exceptions->render(function (\PDOException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $requestId = (string) Str::uuid();
            $sqlState = (string) $exception->getCode();
            $nativeErrorCode = (string) ($exception->errorInfo[1] ?? '');

            Log::error('API database connection failed before query execution.', [
                'request_id' => $requestId,
                'route_name' => $request->route()?->getName() ?? $request->path(),
                'connection_name' => config('database.default'),
                'exception_class' => $exception::class,
                'sql_state' => $sqlState,
                'native_error_code' => $nativeErrorCode,
            ]);

            return response()->json([
                'message' => 'Database unreachable.',
                'request_id' => $requestId,
            ], 503)->header('X-Request-ID', $requestId);
        });
    })->create();
