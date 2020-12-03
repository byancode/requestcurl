# Asynchronous Multi Curl

## Installation

You can install the package via composer:

```bash
composer require byancode/requestcurl
```

## Usage

```php
/***************************************************************************************
@param string   $method   = OPTIONS, GET, POST, PUT, PATCH, DELETE, HEAD, LINK, UNLINK
@param string   $url      = https://...
@param array    $fields   = [ "name" => "byancode" ] or null
@param array    $options  = [ CURLOPT_HTTPHEADER => [ "Accept" => "application/json" ] ]
***************************************************************************************/
$request->add(string $method, string $url, array $fields = null, array $options = [])
$request->get(string $url, array $fields = null, array $options = [])
```

## Usage2

```php
# return string response
$request->get(...)->then(function($data) {
    if (gettype($data) === 'string') {
        echo 'si';
    } else {
        echo 'no';
    }
});
# ----------
# output: si


# return response converted in array
$request->get(...)->then(function(array $data) {
    if (gettype($data) === 'array') {
        echo 'si';
    } else {
        echo 'no';
    }
});
# ----------
# output: si

# return response converted in object
$request->get(...)->then(function(object $data) {
    if (gettype($data) === 'object') {
        echo 'si';
    } else {
        echo 'no';
    }
});
# ----------
# output: si
```

## Example 1

```php
$request = new \Byancode\RequestCurl();

for ($i=0; $i < 16; $i++) {
    $request->post('https://www.php.net/manual/es/function.symlink.php')->then(function($response) {
        echo '1' . PHP_EOL;
        echo str_repeat('-', 20) . PHP_EOL;
        echo $response;
    });
    $request->add('GET', 'https://www.docker.com/get-started')->then(function($response) {
        echo '2' . PHP_EOL;
        echo str_repeat('-', 20) . PHP_EOL;
        echo $response;
    });
    $request->get('https://packagist.org/packages/pbmedia/laravel-ffmpeg')->then(function($response) {
        echo '3' . PHP_EOL;
        echo str_repeat('-', 20) . PHP_EOL;
        echo $response;
    });
}

$request->execute();
```

## Example 2

```php
$request = new \Byancode\RequestCurl();

$request->add('GET', 'https://restcountries.eu/rest/v2/currency/cop')->then(function(array $response): string {
    return $response[0]['name'];
});

$request->get('https://restcountries.eu/rest/v2/currency/pen')->then(function(array $response): string {
    return $response[0]['name'];
});

$request->get('https://restcountries.eu/rest/v2/currency/mxn')->then(function(array $response): string {
    return $response[0]['name'];
});

$request->execute();

print_r($request->response);

/*
output:
--------------------
Array
(
    [0] => Colombia
    [1] => Peru
    [2] => Mexico
)
*/

```
