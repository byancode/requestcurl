<?php
include './src/RequestCurl.php';

$request = new \Byancode\RequestCurl();

$request
    ->get('https://restcountries.eu/rest/v2/currency/97t')
    ->then(function (array $response): string {
        return $response[0]['name'];
    })
    ->catch(function (object $response, array $info): string {
        return $response->message;
    })
    ->finally(function ($name) {
        echo 'Se finalizo con la palabra: ' . $name . PHP_EOL;
    });

$request
    ->get('https://restcountries.eu/rest/v2/currency/pen')
    ->then(function (array $response): string {
        return $response[0]['name'];
    });

$request
    ->get('https://restcountries.eu/rest/v2/currency/mxn')
    ->then(function (array $response): string {
        return $response[0]['name'];
    });

$request->execute();

print_r($request->response);