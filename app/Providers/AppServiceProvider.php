<?php

namespace App\Providers;

use App\Security\JwtAuth;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DB::listen(function (QueryExecuted $query) {
            $debug = env('APP_DEBUG', FALSE);
            if ($debug) {
                Log::channel('stack')->info('{time}: {bindings} > {sql}', [
                    'time' => $query->time, 
                    'bindings' => $query->bindings, 
                    'sql' => $query->sql]
                );
            }
            Log::info('{time}: {bindings} > {sql}', [
                'time' => $query->time, 
                'bindings' => '', 
                'sql' => $query->sql
            ]);
        });

        DB::whenQueryingForLongerThan(500, function (Connection $connection, QueryExecuted $event) {
            Log::info('Query took more than 500 ms: {event}', ['event' => $event]);
        });

        Http::macro('DbService', function () {
            $headers = ["Authorization" => "Bearer " . JwtAuth::getServiceToken()];
            return Http::withHeaders($headers)->withToken(JwtAuth::getServiceToken())
                ->baseUrl(env('DB_SERVICE_URI', '') . '/api')
                ->retry(2, 0, function (Exception $exception, PendingRequest $request) {
                    if (! $exception instanceof RequestException || $exception->response->status() !== 401) {
                        return false;
                    }
                    $request->withToken(JwtAuth::getServiceToken());
                    return true;
                });
            }
        );
    }
}
