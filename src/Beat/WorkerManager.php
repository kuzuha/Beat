<?php

namespace Beat;

class WorkerManager
{
    static public $_router = null;
    static public $_document_root = null;

    static function create_worker(RequestReceiver $receiver)
    {
        self::$_document_root = getcwd();
        $php_bin = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php';
        $descriptor_spec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "w"),
        );
        $env = array(
            'beat_version' => Runner::BEAT_VERSION,
            'beat_headers' => serialize($receiver->_headers),
            'REQUEST_METHOD' => $receiver->_headers['method'],
        );

        $uri = $receiver->_headers['uri'];
        if (self::$_router) {
            $file = $php_bin . ' ' . self::$_router;
        } else {
            $file = self::$_document_root . $uri;
            if ('/' === $file[strlen($file) - 1]) {
                if (file_exists($file . 'index.php')) {
                    $file = $uri . 'index.php';
                } else if (file_exists($file . 'index.html')) {
                    $file = $uri . 'index.html';
                } else if (file_exists($file . 'index.htm')) {
                    $file = $uri . 'index.htm';
                } else {
                    $file = null;
                }
            }
            if (false === file_exists($file)) {
                $response = <<<RESPONSE
HTTP/1.0 404 Not Found
Content-Type: text/plain
Connection: close

404 Beat Not Found.
RESPONSE;
                socket_write($receiver->_socket, $response);
                socket_close($receiver->_socket);
                return;
            } else if (fale === is_file($file)) {
                $response = <<<RESPONSE
HTTP/1.0 403 Forbidden
Content-Type: text/plain
Connection: close

403 Beat Forbidden.
RESPONSE;
                socket_write($receiver->_socket, $response);
                socket_close($receiver->_socket);
                return;
            }
            if (preg_match('/\.php$/', $file)) {
                $file = "$php_bin $file";
            } else {

                if (preg_match('/^WIN/', PHP_OS)) {
                    $file = "type $file";
                } else {
                    $file = "cat $file";
                }
            }
        }
        $file = str_replace('/', DIRECTORY_SEPARATOR, $file);
        $process = proc_open($file, $descriptor_spec, $pipes, self::$_document_root, $env);
        if (false === $process) {
            throw new \Exception("command failed: $file");
        }

        stream_set_blocking($pipes[1], 0);
        stream_set_blocking($pipes[2], 0);
        $tmp = fopen('php://temp', 'w+');
        while (1) {
            $r = array($pipes[1], $pipes[2]);
            $w = $e = null;
            $changed_stream = stream_select($r, $w, $e, NULL);
            foreach ($r as $stream) {
                while ($read = fread($stream, 1024)) {
                    fwrite($tmp, $read);
                }
            }
            $status = proc_get_status($process);
            if (!($status && $status['running'])) {
                fseek($tmp, 0);
                $test = fread($tmp, 13);
                if (0 === preg_match('|^HTTP/\\d\\.\\d \\d{3} $|', $test)) {
                    if ($status['exitcode']) {
                        socket_write($receiver->_socket, "HTTP/1.0 500 Internal Server Error\r\n");
                    } else {
                        socket_write($receiver->_socket, "HTTP/1.0 200 OK\r\n");
                    }
                    $stat = fstat($tmp);
                    socket_write($receiver->_socket, "Content-Length: {$stat['size']}\r\n");
                    socket_write($receiver->_socket, "Connection: close\r\n");
                    socket_write($receiver->_socket, "\r\n");
                }
                $buf_buf = socket_write($receiver->_socket, $test);
                while ($buf = fread($tmp, 1024)) {
                    $res = @socket_write($receiver->_socket, $buf_buf . $buf);
                    if (false === $res) {
                        $buf_buf .= $buf;
                    } else {
                        $buf_buf = "";
                    }
                }
                break;
            }
        }
        socket_close($receiver->_socket);
        return;
    }
}
