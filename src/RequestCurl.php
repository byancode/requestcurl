<?php
namespace Byancode;

class RequestCurl
{
    public $ch = [];
    public $index = 0;
    private $then = [];
    private $catch = [];
    private $finally = [];
    public $response = [];
    const METHODS = ['OPTIONS', 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'LINK', 'UNLINK'];

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

    public function add(string $method, string $url, array $fields = null, array $options = [])
    {
        $method = strtoupper($method);
        if (empty($fields) === false) {
            if ($method !== 'GET') {
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
        $this->ch[$this->index] = curl_init();
        curl_setopt_array($this->ch[$this->index], $options + [
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
    public function execute()
    {
        if ($this->index > 1) {
            $mh = curl_multi_init();
            for ($i = 0; $i < $this->index; $i++) {
                curl_multi_add_handle($mh, $this->ch[$i]);
            }
            # ---------------------------------------
            do {
                $status = curl_multi_exec($mh, $active);
                if ($active) {
                    curl_multi_select($mh);
                }
            } while ($active && $status == CURLM_OK);
            # ---------------------------------------
            for ($i = 0; $i < $this->index; $i++) {
                try {
                    $info = curl_getinfo($this->ch[$i]);
                } catch (\Throwable $th) {
                    $info = [];
                }
                if (curl_errno($this->ch[$i]) === 0) {
                    $this->response[$i] = curl_multi_getcontent($this->ch[$i]);
                    if ($info['http_code'] >= 200 && $info['http_code'] < 300) {
                        $this->promise('then', $i, $info);
                    } else {
                        $this->promise('catch', $i, $info);
                    }
                } else {
                    $error = curl_error($this->ch[$i]);
                    $this->promise('catch', $i, $info, $error);
                }
                $this->promise('finally', $i, $info);
                curl_multi_remove_handle($mh, $this->ch[$i]);
                curl_close($this->ch[$i]);
            }
        } elseif ($this->index === 1) {
            $this->response[0] = curl_exec($this->ch[0]);
            try {
                $info = curl_getinfo($this->ch[0]);
            } catch (\Throwable $th) {
                $info = [];
            }
            if (curl_errno($this->ch[0]) === 0) {
                if ($info['http_code'] >= 200 && $info['http_code'] < 300) {
                    $this->promise('then', 0, $info);
                } else {
                    $this->promise('catch', 0, $info);
                }
            } else {
                $error = curl_error($this->ch[0]);
                $this->promise('catch', 0, $info, $error);
            }
            $this->promise('finally', 0, $info);
            curl_close($this->ch[0]);
        }
        return $this;
    }
    private function promise(string $name, int $index, array $info = [], string $error = null)
    {
        if (array_key_exists($index, $this->{$name}) === false) {
            return false;
        }
        $response = $this->response[$index] ?? '';
        $callback = $this->{$name}[$index] ?? function () {};
        $function = new \ReflectionFunction($callback);
        $argument = $function->getParameters();
        $argument = current($argument);
        if (isset($argument) === true && is_string($response) === true && $argument->hasType()) {
            $type = (string) $argument->getType();
            # ------------------------------------
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
            }
        }
        if ($function->hasReturnType()) {
            $this->response[$index] = $callback($response, $info);
        } else {
            $callback($response, $info);
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
        $this->index = 0;
        $this->response = $this->then = $this->catch = $this->finally = $this->ch = [];
    }
}