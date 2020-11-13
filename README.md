# Asynchronous Multi Curl

## Installation

You can install the package via composer:

```bash
composer require byancode/requestcurl
```

## Usage

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
