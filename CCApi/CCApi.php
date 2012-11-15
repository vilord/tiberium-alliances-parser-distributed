<?php
class CCApi
{

    private $url = null;

    private $curler;

    private $session;
    private $worldSession;

    private $pollRequests = 0;

    public function __construct($url, $worldSession)
    {

        $this->url = $url;
        $this->curler = Curler::create()
            ->setCookieFile(dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "cookies_auth.txt");
        $this->worldSession = $worldSession;
    }

    private function  getData($method, $data = array(), $isRaw = false, $service = "Presentation")
    {
        $data['session'] = $this->session;
        $response = $this->curler
            ->setUrl($this->url . "/$service/Service.svc/ajaxEndpoint/" . $method)
            ->withHeaders(false)
            ->setHeaders(array( /*"Host: $host",*/
            "User-Agent: Mozilla/5.0 (Windows NT 6.1; rv:10.0.2) Gecko/20100101 Firefox/10.0.2",
            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "Accept-Language: en-us,en;q=0.5",
            "Accept-Encoding: gzip, deflate",
            "Content-Type: application/json; charset=UTF-8",
            "X-Qooxdoo-Response-Type: application/json",
//            "Referer: {$this->url}/index.aspx",
            "Pragma: no-cache",
            "Cache-Control: no-cache"
        ))
            ->setPostData(json_encode($data))
            ->post();

        return $isRaw ? $response : json_decode($response);
    }

    public function setSession($ses)
    {
        $this->session = $ses;
    }

    public function isValidSession()
    {
        return $this->getPlayerCount() > 0;
    }

    public function getPlayerCount()
    {
        return $this->getData('RankingGetCount', array(
            "view" => 0,
            "rankingType" => 0)) - 1;
    }

    public function poll($request, $isRaw = false)
    {
        $request['requestid'] = $this->pollRequests;
        $request['sequenceid'] = $this->pollRequests;
        $this->pollRequests++;
        return $this->getData('Poll', $request, $isRaw);
    }

    public function createThread($title, $msg, $forumId)
    {
        $this->getData("CreateForumThread", array(
            "firstPostMessage" => $msg,
            "forumID" => $forumId,
            "subscribe" => true,
            "threadTitle" => $title
        ));
        foreach ($this->getData("GetForumThreads", array(
            "forumId" => $forumId,
            "skip" => 0,
            "take" => 10
        )) as $thread) {
            if ($thread->t == $title) {
                return $thread->i;
            }
        }
    }

    public function addPost($msg, $forumId, $threadId)
    {
        $this->getData("CreateForumPost", array(
            "postMessage" => $msg,
            "forumID" => $forumId,
            "threadID" => $threadId
        ));
    }

    public function getPlayers($from, $to)
    {
        return $this->getData('RankingGetData', array(
                "view" => 0,
                "rankingType" => 0,
                "ascending" => true,
                "firstIndex" => $from,
                "lastIndex" => $to,
                "sortColumn" => 0)
        );
    }

    public function getUserInfo($id)
    {
        return $this->getData('GetPublicPlayerInfo', array("id" => $id));
    }

    public function getServers()
    {
        $data = $this->getData('GetOriginAccountInfo', array(), 1, "Farm");
        print_r($data);
        die;
        return $data;
    }

    public function openSession()
    {
        print_r("Open ingame session ");
        $this->setSession($this->worldSession);
        $data = $this->getData("OpenSession", array(
            "refId" => CCApi::getTime(),
            "reset" => "true",
            "version" => "-1",
            "platformId" => 1
        ));
        $gameSession = $data->i;
        if (!$gameSession || "00000000-0000-0000-0000-000000000000" == $gameSession) {
            print_r("failed\r\n");
            print_r($data);
            return false;
        }
        print_r("$gameSession\r\n");
        $this->setSession($gameSession);
        return true;
    }

    public static function getTime()
    {
        return round(microtime(1) * 1000) * 2;
    }

    public function close()
    {
        $this->curler->close();
    }


    public function register($name)
    {
        if ($this->getData("CreateNewPlayer", array("cityName" => $name, "cityType" => 1, "name" => $name, "startDir" => "rnd"))) {
            print_r("Registered successful\r\n");
            return true;
        }
        print_r("Register fail\r\n");
        return false;

    }
}