<?php

namespace Beat;

class RequestReceiver
{
    /**
     * @internal
     * @var int
     */
    static $_buffer = 1024;
    /**
     * @internal
     * @var bool
     */
    public $_header_reading = true;
    /**
     * @internal
     * @var string
     */
    public $_header = '';
    /**
     * @internal
     * @var array
     */
    public $_headers = array();
    /**
     * @internal
     * @var resource
     */
    public $_body = null;
    /**
     * @internal
     * @var resource
     */
    public $_socket = null;

    function __construct($socket)
    {
        $this->_socket = $socket;
    }

    function receive()
    {
        $is_first = true;
        while ($this->_header_reading) {
            $tmp = socket_read($this->_socket, self::$_buffer, PHP_BINARY_READ);
            if ($tmp === false) {
                if ($is_first) {
                    trigger_error('connection closed.');
                }
                break;
            }
            $is_first = false;
            $last_char = substr($this->_header, -1, 1);
            if (preg_match('/(?:\\r?\\n){2}/', $last_char . $tmp, $matches, PREG_OFFSET_CAPTURE)) {
                $end_pos = $matches[0][1] - strlen($last_char) + strlen($matches[0][0]);
                $this->_header .= substr($tmp, 0, $end_pos);
                rtrim($this->_header, "\r\n");
                $body = substr($tmp, $end_pos);
                $this->parse_header();
                if (isset($this->_headers['content-length'])) {
                    $this->_body = fopen('php://temp', 'w+');
                    fwrite($this->_body, $body);
                }
                $this->_header_reading = false;
                break;
            }
            $this->_header .= $tmp;
        }
        while (true) {
            $tmp = socket_read($this->_socket, self::$_buffer, PHP_BINARY_READ);
            if ($tmp === false || $tmp === '') {
                break;
            }
            fwrite($this->_body, $tmp);
        }
    }

    function parse_header()
    {
        $headers = preg_split('/\\r?\\n/', $this->_header);
        list($method, $uri, $protocol) = explode(' ', array_shift($headers));
        foreach ($headers as $header) {
            if (preg_match('/^([^ ]+) *: *([^ ]+)$/', $header, $matches)) {
                $name = strtolower($matches[1]);
                $value = $matches[2];
                if (isset($this->_headers[$name])) {
                    if (is_array($this->_headers[$name])) {
                        $this->_headers[$name][] = $value;
                    } else {
                        $this->_headers = array($this->_headers[$name], $value);
                    }
                } else {
                    $this->_headers[$name] = $value;
                }
            }
            $this->_headers['method'] = $method;
            $this->_headers['uri'] = $uri;
            $this->_headers['protocol'] = $protocol;
        }

    }

    function is_received()
    {
        if ($this->_headers) {
            if (isset($this->_headers['content-length'])) {
                return ftell($this->_body) >= $this->_headers['content-length'];
            }
            return true;
        }
        return false;
    }
}
