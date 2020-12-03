<?php
include './src/RequestCurl.php';

$request = new \Byancode\RequestCurl();

$request->add('GET', 'https://restcountries.eu/rest/v2/currency/cop')->then(function (array $response): string {
    return $response[0]['name'];
});

$request->get('https://restcountries.eu/rest/v2/currency/pen')->then(function (array $response): string {
    return $response[0]['name'];
});

$request->get('https://restcountries.eu/rest/v2/currency/mxn')->then(function (array $response): string {
    return $response[0]['name'];
});

$request->execute();

print_r($request->response);