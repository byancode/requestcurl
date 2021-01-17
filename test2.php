<?php
include './src/RequestCurl.php';

use Byancode\RequestCurl;

RequestCurl::trace(function () {
    $curl = new RequestCurl();
    $curl->get(
        'https://restcountries.eu/rest/v2/currency/97t'
    )->then(function (array $response): string {
        return $response[0]['name'];
    })->catch(function (object $response, array $info): string {
        return $response->message;
    })->finally(function ($name) {
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
    $curl->get(
        'https://restcountries.eu/rest/v2/currency/pen'
    )->then(function (array $response): string {
        return $response[0]['name'];
    })->catch(function (object $response, array $info): string {
        return $response->message;
    })->finally(function ($name) {
        RequestCurl::http()
            ->get('https://restcountries.eu/rest/v2/currency/clp')
            ->then(function (array $response): string {
                return $response[0]['name'];
            })
            ->finally(function ($name) {
                echo date('H:i:s') . " $name" . PHP_EOL;
            });
        echo date('H:i:s') . " $name" . PHP_EOL;
    });
    $curl->get(
        'https://restcountries.eu/rest/v2/currency/mxn'
    )->then(function (array $response): string {
        return $response[0]['name'];
    })->catch(function (object $response, array $info): string {
        return $response->message;
    })->finally(function ($name) {
        RequestCurl::http()
            ->get('https://restcountries.eu/rest/v2/currency/cny')
            ->then(function (array $response): string {
                return $response[0]['name'];
            })
            ->finally(function ($name) {
                echo date('H:i:s') . " $name" . PHP_EOL;
            });
        echo date('H:i:s') . " $name" . PHP_EOL;
    });

    RequestCurl::http()->get(
        'https://restcountries.eu/rest/v2/currency/cop'
    )->then(function (array $response): string {
        return $response[0]['name'];
    })->finally(function ($name) {
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
});