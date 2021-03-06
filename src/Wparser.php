<?php
error_reporting(E_ALL);
require __DIR__ . '/../vendor/autoload.php';

use limitium\TAPD\CCApi\CCApi;
use limitium\TAPD\CCDecoder\Square;
use limitium\TAPD\CCDecoder\World;
use limitium\TAPD\Util\Timer;
use limitium\zmq\Worker;
use limitium\zmq\ZLogger;


$wrk = new Worker("tcp://localhost:5555", 5000, 10000, null, false);
$log = new ZLogger("wparser", "tcp://localhost:5558");
$log->id = md5(microtime());
$wrk->setExecutor(function ($data) use ($log) {

    Timer::set("start");

    Timer::set("get");

    $server = (array)json_decode($data);

    $api = new CCApi($server["Url"], $server["session"]);
    $result = array(
        "Id" => $server["Id"],
        "status" => 2,
        "data" => null,
    );
    if ($api->openSession()) {
        $world = new World($server["Id"]);

        $time = CCApi::getTime();

        $data = $api->poll(array(
//            "requests" => "WC:A\fCTIME:$time\fCHAT:\fWORLD:\fGIFT:\fACS:0\fASS:0\fCAT:0\f"
            "requests" => "UA\fWC:AC\fTIME:$time\fCHAT:\fWORLD:\fGIFT:\fACS:0\fASS:0\fCAT:0\f"
//            "requests" => "WC:ATIME:" + $time + "CHAT:WORLD:GIFT:ACS:0ASS:0CAT:0"
        ));
        if ($data) {
            foreach ($data as $part) {
                if (isset($part->d->__type)) {
                    if ($part->d->__type == "TIME") {
                        $world->setServerTime($part->d, $time);
                    }
                    if ($part->d->__type == "ENDGAME") {
                        $world->setEndGame($part->d->ch);
                    }
                }
            }

            $successParts = 0;
            $squares = array();
            for ($y = 0; $y <= $server["y"]; $y += 1) {

                $request = $world->request(0, $y, $server["x"], $y);

                $time = CCApi::getTime();
                $resp = $api->poll(array(
                    "requests" => "UA\fWC:AC\fTIME:$time\fCHAT:\fWORLD:$request\fGIFT:\fACS:1\fASS:1\fCAT:1\f"
                ), true);


                if ($resp) {
                    $data = json_decode($resp);
                    if ($data) {
                        print_r("Row: $y");
                        $hasSquares = false;
                        foreach ($data as $part) {
                            if (isset($part->d->__type) && $part->d->__type == "WORLD") {
                                $hasSquares = true;

                                unset($part->d->u);
                                unset($part->d->t);
                                unset($part->d->v);

                                $squaresSize = sizeof($part->d->s);
                                print_r(" squares: " . $squaresSize . "\r\n");
                                if ($squaresSize != $server["x"]) {
                                    break 2;
                                } else {
                                    $successParts++;
                                }
                                foreach ($part->d->s as $squareData) {
                                    $squares[] = $squareData;
                                }
                            }
                        }
                        if (!$hasSquares) {
                            break;
                        }
                    }
                }
            }
        }
        print_r("\r\nSucces parts:$successParts, time: " . Timer::get("get") . "\r\n\r\n");
        if ($successParts == $server["y"]) {
            Timer::set("Encode");
            foreach ($squares as $squareData) {
                $world->addSquare(Square::decode($squareData));
            }
            $zip = gzencode(json_encode($world->prepareData()));
            print_r("Encoded, time: " . Timer::get("Encode") . " \r\n\r\n");
            $result["status"] = 1;
            $result["data"] = $zip;

            $totalTime = Timer::get("start");
            print_r("Total time world " . $server["Id"] . ": " . $totalTime . "\r\n\r\n");
            $log->info($totalTime, ['id' => $log->id]);
        } else {
            $log->warning("parse_fail", ['id' => $log->id]);
        }

    } else {
        $result["status"] = 3;
        $log->warning("ses_drop");
    }
    $api->close();
    return sprintf("%03s", $result["Id"]) . sprintf("%02s", $result["status"]) . $result["data"];
});

$wrk->work();


