<?php
namespace Byancode;

class RequestCurl
{
    const METHODS = ['OPTIONS', 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'LINK', 'UNLINK'];

    protected $noStaticCurl = [
        'curl' => [],
        'index' => 0,
        'then' => [],
        'catch' => [],
        'finally' => [],
        'response' => [],
    ];
    protected static $staticCurl = [
        'curl' => [],
        'index' => 0,
        'then' => [],
        'catch' => [],
        'finally' => [],
        'response' => [],
    ];

    public static $isIsolate = false;

    public static function enableIsolate()
    {
        static::$isIsolate = true;
    }
    public static function disableIsolate()
    {
        static::$isIsolate = false;
        static::restoreIsolate();
    }
    public static function restoreIsolate()
    {
        static::$staticCurl = [
            'curl' => [],
            'index' => 0,
            'then' => [],
            'catch' => [],
            'finally' => [],
            'response' => [],
        ];
    }
    public static function isolate(callable $callbackWithIsolate)
    {
        static::enableIsolate();
        $callbackWithIsolate();
        (new self())->execute(true);
        static::disableIsolate();
    }

    public function index()
    {
        return static::$isIsolate ? static::$staticCurl['index'] : $this->noStaticCurl['index'];
    }
    public function increment()
    {
        if (static::$isIsolate === true) {
            static::$staticCurl['index']++;
        } else {
            $this->noStaticCurl['index']++;
        }
    }
    public function curl(int $index = null)
    {
        if (static::$isIsolate === true) {
            return static::$staticCurl['curl'][$index ?? static::$staticCurl['index']];
        } else {
            return $this->noStaticCurl['curl'][$index ?? $this->noStaticCurl['index']];
        }
    }
    public function insertCurl(array $options)
    {
        if (static::$isIsolate) {
            static::$staticCurl['curl'][static::$staticCurl['index']] = curl_init();
            curl_setopt_array(static::$staticCurl['curl'][static::$staticCurl['index']], $options);
            static::$staticCurl['index']++;
        } else {
            $this->noStaticCurl['curl'][$this->noStaticCurl['index']] = curl_init();
            curl_setopt_array($this->noStaticCurl['curl'][$this->noStaticCurl['index']], $options);
            $this->noStaticCurl['index']++;
        }
    }
    public function value(string $key)
    {
        if (static::$isIsolate === true) {
            return static::$staticCurl[$key];
        } else {
            return $this->noStaticCurl[$key];
        }
    }
    public function arrayValue(string $key, int $index)
    {
        if (static::$isIsolate === true) {
            return static::$staticCurl[$key][$index];
        } else {
            return $this->noStaticCurl[$key][$index];
        }
    }
    public function variable(string $key, $value)
    {
        if (static::$isIsolate === true) {
            return static::$staticCurl[$key] = $value;
        } else {
            return $this->noStaticCurl[$key] = $value;
        }
    }
    public function promiseCaller(string $key, $value)
    {
        if (static::$isIsolate === true) {
            static::$staticCurl[$key][static::$staticCurl['index'] - 1] = $value;
        } else {
            $this->noStaticCurl[$key][$this->noStaticCurl['index'] - 1] = $value;
        }
    }

    public static function unparse_url($parsed_url)
    {
        $scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $user = isset($parsed_url['user']) ? $parsed_url['user'] : '';
        $pass = isset($parsed_url['pass']) ? ':' . $parsed_url['pass'] : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $query = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
        return "$scheme$user$pass$host$port$path$query$fragment";
    }

    public function add(string $method, string $url, $fields = null, array $options = [])
    {
        $method = strtoupper($method);
        if (empty($fields) === false) {
            if ($method !== 'GET') {
                if (is_object($fields)) {
                    $fields = json_encode($fields);
                } elseif (is_array($fields)) {
                    $fields = http_build_query($fields);
                }
                $options[CURLOPT_POSTFIELDS] = $fields;
            } else {
                $parsed_url = parse_url($url);
                if (isset($parsed_url['query']) && empty($parsed_url['query']) === false) {
                    parse_str($parsed_url['query'], $parsed_str);
                    $fields = array_merge($parsed_str, $fields);
                }
                $parsed_url['query'] = http_build_query($fields);
                $url = self::unparse_url($parsed_url);
            }
        }
        $this->insertCurl($options + [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_AUTOREFERER => true,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_VERBOSE => 0,
            CURLOPT_HTTPHEADER => [],
        ]);
        return $this;
    }
    public function then(callable $callback)
    {
        $this->promiseCaller('then', $callback);
        return $this;
    }
    function catch (callable $callback) {
        $this->promiseCaller('catch', $callback);
        return $this;
    }
    function finally (callable $callback) {
        $this->promiseCaller('finally', $callback);
        return $this;
    }
    public function response(int $index = null)
    {
        if (static::$isIsolate === true) {
            return isset($index) ? static::$staticCurl['response'][$index] : static::$staticCurl['response'];
        } else {
            return isset($index) ? $this->noStaticCurl['response'][$index] : $this->noStaticCurl['response'];
        }
    }
    public function responseValue(int $index, $value)
    {
        if (static::$isIsolate === true) {
            static::$staticCurl['response'][$index] = $value;
        } else {
            $this->noStaticCurl['response'][$index] = $value;
        }
    }
    public function execute(bool $force = false)
    {
        if (static::$isIsolate && !$force) {
            return;
        }
        if ($this->index() > 1) {
            $mh = curl_multi_init();
            for ($i = 0; $i < $this->index(); $i++) {
                curl_multi_add_handle($mh, $this->curl($i));
            }
            # ---------------------------------------
            do {
                $status = curl_multi_exec($mh, $active);
                if ($active) {
                    curl_multi_select($mh);
                }
            } while ($active && $status == CURLM_OK);
            # ---------------------------------------
            for ($i = 0; $i < $this->index(); $i++) {
                try {
                    $info = curl_getinfo($this->curl($i));
                } catch (\Throwable $th) {
                    $info = [];
                }
                if (curl_errno($this->curl($i)) === 0) {
                    $this->responseValue($i, curl_multi_getcontent($this->curl($i)));
                    if ($info['http_code'] >= 200 && $info['http_code'] < 300) {
                        $this->promise('then', $i, $info);
                    } else {
                        $this->promise('catch', $i, $info);
                    }
                } else {
                    $error = curl_error($this->curl($i));
                    $this->promise('catch', $i, $info, $error);
                }
                $this->promise('finally', $i, $info);
                curl_multi_remove_handle($mh, $this->curl($i));
                curl_close($this->curl($i));
            }
        } elseif ($this->index() === 1) {
            $this->responseValue(0, curl_exec($this->curl(0)));
            try {
                $info = curl_getinfo($this->curl(0));
            } catch (\Throwable $th) {
                $info = [];
            }
            if (curl_errno($this->curl(0)) === 0) {
                if ($info['http_code'] >= 200 && $info['http_code'] < 300) {
                    $this->promise('then', 0, $info);
                } else {
                    $this->promise('catch', 0, $info);
                }
            } else {
                $error = curl_error($this->curl(0));
                $this->promise('catch', 0, $info, $error);
            }
            $this->promise('finally', 0, $info);
            curl_close($this->curl(0));
        }
        return $this;
    }
    private function promise(string $name, int $index, array $info = [], string $error = null)
    {
        if (array_key_exists($index, $this->value($name)) === false) {
            return false;
        }
        $callback = $this->arrayValue($name, $index);
        $response = $this->response($index);
        $function = new \ReflectionFunction($callback);
        $argument = $function->getParameters();
        [$argument] = $function->getParameters() + [null];
        if (isset($argument) === true && $argument->hasType()) {
            $type = (string) ($argument->getType() ? $argument->getType()->getName() : null);
            # ------------------------------------
            if (is_string($response) === true) {
                if ($type === 'array') {
                    $response = json_decode($response, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $response = [];
                    }
                } elseif ($type === 'object') {
                    $response = json_decode($response);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $response = (object) [];
                    }
                } else {
                    $response = call_user_func("{$type}val", $response);
                }
            } elseif (is_array($response) === true) {
                if ($type === 'string') {
                    $response = json_encode($response);
                } elseif ($type === 'object') {
                    $response = (object) $response;
                } else {
                    $response = call_user_func("{$type}val", $response);
                }
            } elseif (is_object($response) === true) {
                if ($type === 'string') {
                    $response = json_encode($response);
                } elseif ($type === 'array') {
                    $response = (array) $response;
                } else {
                    $response = call_user_func("{$type}val", $response);
                }
            }
        }
        if ($function->hasReturnType()) {
            $this->responseValue($index, $callback($response, $info, $error));
        } else {
            $callback($response, $info, $error);
        }
    }
    public function __call($name, $arguments)
    {
        if (in_array(strtoupper($name), self::METHODS)) {
            array_unshift($arguments, $name);
            return call_user_func_array([$this, 'add'], $arguments);
        } elseif ($name == 'custom') {
            return call_user_func_array([$this, 'add'], $arguments);
        }
    }
    public static function __callStatic($name, $arguments)
    {
        $request = new self();
        if (in_array(strtoupper($name), self::METHODS)) {
            array_unshift($arguments, $name);
            return call_user_func_array([$request, 'add'], $arguments)->execute()->response(0);
        } elseif ($name == 'custom') {
            return call_user_func_array([$request, 'add'], $arguments)->execute()->response(0);
        }
    }
    public function __destruct()
    {
        $this->noStaticCurl = [
            'curl' => [],
            'index' => 0,
            'then' => [],
            'catch' => [],
            'finally' => [],
            'response' => [],
        ];
    }
}