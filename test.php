<?php
include './src/RequestCurl.php';

use Byancode\RequestCurl;

RequestCurl::trace(function () {
    $request = new RequestCurl();
    $request
        ->get('https://restcountries.eu/rest/v2/currency/97t', [
            'hola' => 'yes',
        ], [
            CURLOPT_HTTPHEADER => [
                'accept' => 'application/json, text/plain, */*',
                'accept-language' => 'es-ES,es;q=0.9',
                'content-type' => 'application/json;charset=UTF-8',
                'event-origin' => 'web-aliados-lite',
                'app-version' => '30',
            ],
        ])
        ->then(function (array $response): string {
            return $response[0]['name'];
        })
        ->catch(function (object $response, array $info): string {
            return $response->message;
        })
        ->finally(function ($name) {

            $request = new RequestCurl();
            $request
                ->get('https://restcountries.eu/rest/v2/currency/pen')
                ->then(function (array $response): string {
                    return $response[0]['name'];
                })
                ->finally(function ($name) {
                    echo '2 ' . $name . PHP_EOL;
                });
            $request->execute();
            echo '1 ' . $name . PHP_EOL;
            echo 'Se finalizo con la palabra: ' . $name . PHP_EOL;
        });
    $request->execute();

    $request = new RequestCurl();
    $request
        ->get('https://restcountries.eu/rest/v2/currency/pen')
        ->then(function (array $response): string {
            return $response[0]['name'];
        })
        ->finally(function ($name) {
            echo '2 ' . $name . PHP_EOL;
        });
    $request->execute();

    $request = new RequestCurl();
    $request
        ->get('https://restcountries.eu/rest/v2/currency/mxn')
        ->then(function (array $response): string {
            return $response[0]['name'];
        })
        ->finally(function ($name) {
            echo '3 ' . $name . PHP_EOL;
        });
    $request->execute();
});