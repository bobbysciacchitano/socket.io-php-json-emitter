<?php

/**
 * Created by PhpStorm.
 * User: Johnny
 * Date: 04.04.2015
 * Time: 15:03
 */
namespace SocketIO;

use Exception;

class Emitter
{
    private $redis;
    private $rooms;
    private $nsp;
    private $flags;
    private $uid;
    private $key;

    /**
     * @param bool|array $redis Redis-Client or Array of options
     * @param array $opts Array of options
     * @throws Exception
     */
    function __construct($redis = false, $opts = array())
    {
        if (is_array($redis))
        {
            $opts = $redis;
            $redis = false;
        }

        $opts = array_merge(array("host" => "localhost", "port" => 6379), $opts);

        if (!$redis)
        {
            $redis = new \Redis();
            if (isset($opts["socket"]))
                $redis->connect($opts["socket"]);
            else
                $redis->connect($opts["host"], $opts["port"]);
        }
        $this->redis = $redis;

        if (!is_callable(array($this->redis, "publish")))
            throw new \Exception("The Redis client provided is invalid. The client needs to implement the publish method. Try using the default client.");

        $this->rooms = array();
        $this->flags = array();
        $this->nsp = "/";
        $this->key = isset($opts["key"]) ? $opts["key"] : "socket.io";
        $this->uid = uniqid();
    }

    /**
     * @param $key string New key (Channel-Name beginning, default: socket.io)
     */
    function setKey($key)
    {
        $this->key = $key;
    }

    /**
     * @return string Current key (Channel-Name beginning, default: socket.io)
     */
    function getKey()
    {
        return $this->key;
    }

    /**
     * @param $room string Room-Name
     * @return $this Emitter
     */
    function in($room)
    {
        if (!in_array($room, $this->rooms))
            array_push($this->rooms, $room);

        return $this;
    }

    /**
     * @param $room string Room-Name
     * @return Emitter
     */
    public function to($room)
    {
        return $this->in($room);
    }

    /**
     * @param $nsp string Namespace
     * @return $this Emitter
     */
    public function of($nsp) {
        $this->nsp = $nsp;
        return $this;
    }

    /**
     * @param $event string Name of event
     * @param $data array Data
     */
    public function emit($event, $data)
    {
        $packet = array();
        $packet["type"] = 2;
        $packet["data"] = array();
        $packet["data"][0] = $event;
        $packet["data"][1] = $data;
        $packet["nsp"] = $this->nsp;

        $this->redis->publish($this->key . "#" . $this->nsp . "#", msgpack_pack([ uniqid() ,$packet, [
            "rooms" => $this->rooms,
            "flags" => $this->flags
        ] ]));

        $this->rooms = array();
        $this->flags = array();
        $this->nsp = "/";
    }
}