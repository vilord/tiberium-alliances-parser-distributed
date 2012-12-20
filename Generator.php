<?php
require_once "Util/Curler.php";
require_once "Util/Timer.php";
require_once "CCAuth/CCAuth.php";
require_once "lib/0MQ/0MQ/Log.php";

class Generator
{
    private $servers;
    private $keys = array();
    private $sessions = array();
    private $log;

    public function __construct()
    {
        $this->servers = require dirname(__FILE__) . DIRECTORY_SEPARATOR . "servers.php";
        $this->log = new Log("tcp://192.168.123.2:5558", "auth");
    }

    public function nextServer()
    {
        if (sizeof($this->keys) == 0) {
            $this->keys = array_keys($this->servers);
        }
        while (!is_numeric($id = array_shift($this->keys))) {
        }
        $server = $this->servers[$id];
        $session = $this->getSession($server["u"]);
        if ($session) {
            $server["session"] = $session;
            return $server;
        }
        return false;
    }

    private function getSession($username)
    {
        if (!isset($this->sessions[$username])) {
            $this->sessions[$username] = array(
                "auth" => new CCAuth($username, "qweqwe123"),
                "forceReload" => true
            );
        }
        Timer::set("session");
        $session = $this->sessions[$username]["auth"]->getSession($this->sessions[$username]["forceReload"]);
        if ($session) {
            $this->sessions[$username]["forceReload"] = false;
            $this->log->info(Timer::get("session"));
        } else {
            $this->log->warn("ses_fail");
        }
        return $session;
    }

    public function reloadSession($serverId)
    {
        $username = $this->servers[$serverId]["u"];
        if (isset($this->sessions[$username])) {
            $this->sessions[$username]["forceReload"] = true;
        }
    }
}
