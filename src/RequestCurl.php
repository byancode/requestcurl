<?php
namespace Byancode;

class RequestCurl
{
    public $ch = [];
    public $index = 0;
    public $response = [];
    private $then = [];
    public $catch = [];
    public $finally = [];
    const METHODS = ['OPTIONS', 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'LINK', 'UNLINK'];

    public function add(string $method, string $url, array $fields = null, array $options = [])
    {
        $method = strtoupper($method);
        if ($fields && $method !== 'GET') {
            $options[CURLOPT_POSTFIELDS] = $fields;
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
                $info = curl_getinfo($this->ch[$i]);
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
        } else {
            $this->response[0] = curl_exec($this->ch[0]);
            $info = curl_getinfo($this->ch[0]);
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
        $response = $this->response[$index] ?? '';
        $callback = $this->{$name}[$index] ?? function () {};
        $function = new \ReflectionFunction($callback);
        $argument = $function->getParameters();
        $argument = current($argument);
        if ($argument) {
            if (is_string($response) === true) {
                switch ((string) $argument->getType()) {
                    case 'array':
                        $response = json_decode($response, true);
                        break;
                    case 'object':
                        $response = json_decode($response);
                        break;
                }
            }
            if ($function->hasReturnType()) {
                $this->response[$index] = $callback($response, $info);
            } else {
                $callback($response, $info);
            }
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
        $this->response = $this->then = $this->catch = $this->ch = [];
    }
}