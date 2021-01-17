<?php
namespace Byancode;

use ReflectionFunction;
use Throwable;

class RequestCurl
{
    const METHODS = [
        'OPTIONS',
        'UNLINK',
        'DELETE',
        'PATCH',
        'POST',
        'HEAD',
        'LINK',
        'GET',
        'PUT',
    ];

    private $alone;
    private $ch = [];
    private $loaded;
    private $index = 0;
    private $then = [];
    private $catch = [];
    private $finally = [];
    public $response = [];
    private $tracked = false;
    protected $executed = false;

    public static $logger;
    public static $pending = [];
    public static $tracing = false;

    public static function report(callable $callback)
    {
        self::$logger = $callback;
    }

    public static function http(bool $alone = false)
    {
        return new static($alone);
    }

    public static function trace(callable $callback)
    {
        static::enableTrace();
        $callback();
        static::disableTrace();
    }

    public static function handler()
    {
        while (true) {
            $list = static::$pending;

            if (count($list) === 0) {
                break;
            }

            static::$pending = [];

            $temp = new static(true);

            foreach ($list as $instance) {
                $temp->import($instance);
            }

            $temp->execute();

            foreach ($list as $instance) {
                $instance->trigger();
            }
        }
    }

    public static function enableTrace()
    {
        static::$tracing = true;
    }

    public static function disableTrace()
    {
        self::handler();
        static::$tracing = false;
    }

    public static function withHeaders(array $headers)
    {
        return (new self())->headers($headers);
    }

    public function __construct(bool $alone = false)
    {
        $this->tracked = static::$tracing;
        if (static::$tracing && !$alone) {
            self::$pending[] = $this;
        }
        $this->alone = $alone;
    }

    public function get_deep_trace(array $trace)
    {
        return isset($trace['class']) && isset($trace['function']) &&
        $trace['class'] === __CLASS__ && $trace['function'] === '__construct';
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

    public function export()
    {
        return $this->ch;
    }

    public function import($instance)
    {
        $this->ch = array_merge($this->ch, $instance->export());
        $this->index = count($this->ch);
        return $this;
    }

    public function headers(array $headers = [])
    {
        foreach ($headers as $key => $value) {
            $this->headers[$key] = $value;
        }
        return $this;
    }

    protected function headerList()
    {
        $result = [];
        foreach ($this->headers as $key => $value) {
            $result[] = "$key: $value";
        }
        return $result;
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
        if (count($this->headers) > 0) {
            if (isset($options[CURLOPT_HTTPHEADER])) {
                $options[CURLOPT_HTTPHEADER] = array_merge($this->headerList(), $options[CURLOPT_HTTPHEADER]);
            } else {
                $options[CURLOPT_HTTPHEADER] = $this->headerList();
            }
        }
        $curl = curl_init();
        curl_setopt_array($curl, $options + [
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
        $this->ch[$this->index] = [$curl, $this, $this->index];
        $this->index++;
        return $this;
    }
    public function then(callable $callback)
    {
        $this->then[$this->index - 1] = $callback;
        return $this;
    }
    function catch (callable $callback) {
        $this->catch[$this->index - 1] = $callback;
        return $this;
    }
    function finally (callable $callback) {
        $this->finally[$this->index - 1] = $callback;
        return $this;
    }
    public function response(int $index = null)
    {
        return isset($index) ? $this->response[$index] : $this->response;
    }
    public function trigger()
    {
        $this->loaded && call_user_func($this->loaded, $this->response);
    }
    public function loaded(callable $callback)
    {
        $this->loaded = $callback;
        return $this;
    }
    public function execute()
    {
        $this->executed = true;
        # -------------------------------------------
        if ($this->tracked && !$this->alone) {
            return $this;
        }
        # -------------------------------------------
        if ($this->index > 1) {
            $mh = curl_multi_init();
            # ---------------------------------------
            foreach ($this->ch as [$ch, $instance, $index]) {
                curl_multi_add_handle($mh, $ch);
            }
            # ---------------------------------------
            do {
                $status = curl_multi_exec($mh, $active);
                if ($active) {
                    curl_multi_select($mh);
                }
            } while ($active && $status == CURLM_OK);
            # ---------------------------------------
            foreach ($this->ch as [$ch, $instance, $index]) {
                try {
                    $info = curl_getinfo($ch);
                } catch (Throwable $th) {
                    $info = [];
                }
                if (curl_errno($ch) === 0) {
                    $instance->response[$index] = curl_multi_getcontent($ch);
                    if ($info['http_code'] >= 200 && $info['http_code'] < 300) {
                        $instance->promise('then', $index, $info);
                    } else {
                        $instance->promise('catch', $index, $info);
                    }
                } else {
                    $error = curl_error($ch);
                    $instance->promise('catch', $index, $info, $error);
                }
                $instance->promise('finally', $index, $info);
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }
        } elseif ($this->index === 1) {
            [$ch, $instance, $index] = current($this->ch);
            $instance->response[$index] = curl_exec($ch);
            if (curl_errno($ch) === 0) {
                $info = curl_getinfo($ch);
                if ($info['http_code'] >= 200 && $info['http_code'] < 300) {
                    $instance->promise('then', $index, $info);
                } else {
                    $instance->promise('catch', $index, $info);
                }
            } else {
                $info = [];
                $error = curl_error($ch);
                $instance->promise('catch', $index, $info, $error);
            }
            $instance->promise('finally', $index, $info);
            curl_close($ch);
        }
        return $this;
    }
    public function promise(string $event, int $index, array $info = [], string $error = null)
    {
        if (array_key_exists($index, $this->{$event}) === false) {
            return false;
        }
        $callback = $this->{$event}[$index];
        $response = $this->response[$index];
        $function = new ReflectionFunction($callback);
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

        try {
            $response = $callback($response, $info, $error);
        } catch (\Throwable $th) {
            self::$logger && call_user_func(self::$logger, $th);
            return;
        }
        if ($function->hasReturnType()) {
            $this->response[$index] = $response;
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
        if (!$this->executed) {
            $this->execute();
        }

        $this->index = 0;
        $this->response = $this->then = $this->catch = $this->finally = $this->ch = [];
    }
}