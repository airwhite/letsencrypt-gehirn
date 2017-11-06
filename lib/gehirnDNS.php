<?php
//
//  Gehirn DNS Web Service API Wrapper Class
//

define("GEHIRN_API_URL",    "https://api.gis.gehirn.jp/dns/v1/zones");
define("GEHIRN_API_TOKEN",  "Gehirn API Token");
define("GEHIRN_API_SECRET", "Gehirn API Secret");

class GehirnDNS {
    private $debug = false;
    private $zoneId = "";
    private $zoneName = "";
    private $verId = "";
    private $verEditable = false;

    function __construct($domain, $debug=false) {
        $this->debug = $debug;
        $zones = $this->apiZoneList();
        foreach ($zones as $zone) {
            if ($zone["name"] == $domain) {
                $this->zoneId      = $zone["id"];
                $this->zoneName    = $zone["name"];
                $this->verId       = $zone["current_version_id"];
                $this->verEditable = $zone["current_version"]["editable"];
            }
        }
        if ($this->debug) {
            $this->console("   zone id: ".$this->zoneId);
            $this->console(" zone name: ".$this->zoneName);
            $this->console("version id: ".$this->verId);
            $this->console("  editable: ".var_export($this->verEditable,true));
        }
    }

    function getHostIPv4($name) {
        return $this->getRecord("A", $name);
    }

    function getHostIPv6($name) {
        return $this->getRecord("AAAA", $name);
    }

    function addHostIPv4($name, $ipv4, $ttl=3600) {
        $json  = '{"type":"A","name":"'.$name.'","enable_alias":false,';
        $json .= '"ttl":'.$ttl.',"records":[{"address":"'.$ipv4.'"}]}';
        return $this->addRecord($json);
    }

    function addHostIPv6($name, $ipv6, $ttl=3600) {
        $json  = '{"type":"AAAA","name":"'.$name.'","enable_alias":false,';
        $json .= '"ttl":'.$ttl.',"records":[{"address":"'.$ipv6.'"}]}';
        return $this->addRecord($json);
    }

	function editHostIPv4($name, $ipv4, $ttl=3600) {
		$rec   = $this->getRecord("A", $name);
		$recId = $rec["id"];
        $json  = '{"id":"'.$recId.'","type":"A","name":"'.$name.'",';
        $json .= '"enable_alias":false,"ttl":'.$ttl.',';
        $json .= '"records":[{"address":"'.$ipv4.'"}]}';
        return $this->editRecord($recId, $json);
    }

    function editHostIPv6($name, $ipv6, $ttl=3600) {
        $rec   = $this->getRecord("AAAA", $name);
        $recId = $rec["id"];
 		$json  = '{"id":"'.$recId.'","type":"AAAA","name":"'.$name.'",';
        $json .= '"enable_alias":false,"ttl":'.$ttl.',';
        $json .= '"records":[{"address":"'.$ipv6.'"}]}';
        return $this->editRecord($recId, $json);
    }

    function deleteHostIPv4($name) {
        $rec   = $this->getRecord("A", $name);
        $recId = $rec["id"];
 		return $this->deleteRecord($recId);
    }

    function deleteHostIPv6($name) {
        $rec   = $this->getRecord("AAAA", $name);
        $recId = $rec["id"];
 		return $this->deleteRecord($recId);
    }

    function getTXT($name) {
        return $this->getRecord("TXT", $name);
    }

    function addTXT($name, $data, $ttl=3600) {
        $json  = '{"type":"TXT","name":"'.$name.'","enable_alias":false,';
        $json .= '"ttl":'.$ttl.',"records":[{"data":"'.$data.'"}]}';
        return $this->addRecord($json);
    }

    function editTXT($name, $data, $ttl=3600) {
        $rec   = $this->getRecord("TXT", $name);
        $recId = $rec["id"];
 		$json  = '{"id":"'.$recId.'","type":"TXT","name":"'.$name.'",';
        $json .= '"enable_alias":false,"ttl":'.$ttl.',';
        $json .= '"records":[{"data":"'.$data.'"}]}';
        return $this->editRecord($recId, $json);
    }

    function deleteTXT($name) {
        $rec   = $this->getRecord("TXT", $name);
        $recId = $rec["id"];
 		return $this->deleteRecord($recId);
    }

    private function getRecords() {
        return $this->apiRecordList();
    }

    private function getRecord($type, $name) {
        $recs = $this->apiRecordList();
        foreach ($recs as $rec) {
            if ($rec["type"] == $type && $rec["name"] == $name) return $rec;
        }
        return false;
    }

    private function addRecord($json) {
        if (!$this->verEditable) $this->console("not editable",1);
        $res = $this->apiRecordAdd($json);
        if (isset($res["code"])) $this->console("addRecord error: ".var_export($res,true),1);
        return $res["id"];
    }

    private function editRecord($recId, $json) {
        if (!$this->verEditable) $this->console("not editable",1);
        $res = $this->apiRecordEdit($recId, $json);
        if (isset($res["code"])) $this->console("editRecord error: ".var_export($res,true),1);
        return $res["id"];
    }

    private function deleteRecord($recId) {
        if (!$this->verEditable) $this->console("not editable",1);
        $res = $this->apiRecordDelete($recId);
        if (isset($res["code"])) $this->console("deleteRecord error: ".var_export($res,true),1);
        return $res["id"];
    }

    private function apiZoneList() {
        return $this->curl("GET");
    }

    private function apiRecordList() {
        return $this->curl("GET", "/".$this->zoneId."/versions/".$this->verId."/records");
    }

    private function apiRecordAdd($json) {
        return $this->curl("POST", "/".$this->zoneId."/versions/".$this->verId."/records", $json);
    }

    private function apiRecordEdit($recId, $json) {
        return $this->curl("PUT", "/".$this->zoneId."/versions/".$this->verId."/records/".$recId, $json);
    }

    private function apiRecordDelete($recId) {
        return $this->curl("DELETE", "/".$this->zoneId."/versions/".$this->verId."/records/".$recId);
    }

    // common curl function
    private function curl($method, $dir="", $json="") {
        if ($this->debug) $this->console("curl method=$method url=".GEHIRN_API_URL.$dir." json=$json");
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, GEHIRN_API_URL.$dir);
        switch (strtoupper($method)) {
            case "GET":
                break;
            case "POST":
                curl_setopt($ch, CURLOPT_POST, 1);
                break;
            case "PUT":
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                break;
            case "DELETE":
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                break;
            default:
                $this->console("curl method error: $method ".GEHIRN_API_URL.$dir, 1);
        }
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERPWD, GEHIRN_API_TOKEN.":".GEHIRN_API_SECRET);
        if ($json != "") {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }
		$result = curl_exec($ch);
		sleep(5);
        if ($this->debug) $this->console("curl response=".var_export($result,true));
        if ($result === false) $this->console("curl error: ".curl_error($ch), 1);
        curl_close($ch);
        $result = json_decode($result, true);
        if (json_last_error() != JSON_ERROR_NONE) $this->console("json_decode: ".json_last_error_msg(),1);
        return $result;
    }

    // console log
    private function console($msg, $exit=0) {
        if (defined("PHP_WINDOWS_VERSION_MAJOR"))
            $msg = mb_convert_encoding($msg, "CP932", "UTF-8");
        echo date("Y-m-d H:i:s ").$msg."\n";
        if ($exit != 0) exit($exit);
    }
}
?>
