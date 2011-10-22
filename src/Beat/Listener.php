<?php

namespace Beat;

class Listener
{
    static $_buffer = 10;

    static function listen($host, $port)
    {
        $socket = self::_create_socket($host, $port);

        $clients = array();
        $receivers = array();
        while (1) {
            $changed_socket = self::_wait_sockets_change($socket, $clients);
            if (count($changed_socket) === 0) {
                continue;
            }

            if (false !== ($key = array_search($socket, $changed_socket, true))) {
                self::_accept($socket, $clients, $receivers);
                unset($changed_socket[$key]);
            }

            foreach ($changed_socket as $i => $read) {
                /* @var $receiver \Beat\RequestReceiver */
                $receiver = $receivers[$i];
                $receiver->receive();
                if ($receivers[$i]->is_received()) {
                    WorkerManager::create_worker($receiver);
                    unset($clients[array_search($read, $clients, true)]);
                }
            }
        }
    }

    static function _create_socket($host, $port)
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_bind($socket, $host, $port);
        socket_listen($socket);
        socket_set_nonblock($socket);
        return $socket;
    }

    static function _accept($socket, &$clients, &$receivers)
    {
        $clients[] = $newbie = socket_accept($socket);
        socket_set_nonblock($newbie);
        $receivers[] = new RequestReceiver($newbie);
    }

    static function _wait_sockets_change($socket, $clients)
    {
        $clients[] = $socket;
        $w = $e = null;
        $changed = socket_select($clients, $w, $e, null);
        if (false === $changed) {
            throw new \Exception('socket_select failed: ' . socket_strerror(socket_last_error()));
        }
        return $clients;
    }
}
