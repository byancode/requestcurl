<?php
include './src/RequestCurl.php';

use Byancode\RequestCurl;

RequestCurl::enableTrace();

RequestCurl::http()
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

        RequestCurl::http()
            ->get('https://restcountries.eu/rest/v2/currency/cop')
            ->then(function (array $response): string {
                return $response[0]['name'];
            })
            ->finally(function ($name) {
                echo date('H:i:s') . " $name" . PHP_EOL;
            });
        echo date('H:i:s') . " $name" . PHP_EOL;
        echo 'Se finalizo con la palabra: ' . $name . PHP_EOL;
    });

RequestCurl::http()
    ->get('https://restcountries.eu/rest/v2/currency/pen')
    ->then(function (array $response): string {
        return $response[0]['name'];
    })
    ->finally(function ($name) {
        RequestCurl::http()
            ->get('https://restcountries.eu/rest/v2/currency/eur')
            ->then(function (array $response): string {
                return $response[0]['name'];
            })
            ->finally(function ($name) {
                echo date('H:i:s') . " $name" . PHP_EOL;
            });
        echo date('H:i:s') . " $name" . PHP_EOL;
    });

RequestCurl::http()
    ->get('https://restcountries.eu/rest/v2/currency/mxn')
    ->then(function (array $response): string {
        return $response[0]['name'];
    })
    ->finally(function ($name) {
        RequestCurl::http()
            ->get('https://restcountries.eu/rest/v2/currency/usd')
            ->then(function (array $response): string {
                return $response[0]['name'];
            })
            ->finally(function ($name) {
                echo date('H:i:s') . " $name" . PHP_EOL;
            });
        echo date('H:i:s') . " $name" . PHP_EOL;
    });

RequestCurl::disableTrace();