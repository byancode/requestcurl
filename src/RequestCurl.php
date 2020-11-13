<?php
namespace Byancode;

class RequestCurl
{
    public $ch = [];
    public $index = 0;
    public $result = [];
    private $callback = [];
    const METHODS = ['OPTIONS', 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'LINK', 'UNLINK'];

    public function add(string $method, string $url, array $fields = null, array $options = [])
    {
        $this->ch[$this->index] = curl_init();
        curl_setopt_array($this->ch[$this->index], $options + ($fields ? [
            CURLOPT_POSTFIELDS => $fields,
        ] : []) + [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
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
        $this->callback[$this->index - 1] = $callback;
        return $this;
    }
    public function response(int $index = null)
    {
        return isset($index) ? $this->result[$index] : $this->result;
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
                $this->result[$i] = curl_multi_getcontent($this->ch[$i]);
                if (isset($this->callback[$i])) {
                    $this->callback[$i]($this->result[$i]);
                }
                curl_multi_remove_handle($mh, $this->ch[$i]);
                curl_close($this->ch[$i]);
            }
        } else {
            $this->result[0] = curl_exec($this->ch[0]);
            $this->result[0] = json_decode($this->result[0], true);
            if (isset($this->callback[0])) {
                $this->callback[0]($this->result[0]);
            }
            curl_close($this->ch[0]);
        }
        return $this;
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
        $this->result = $this->callback = $this->ch = [];
    }
}