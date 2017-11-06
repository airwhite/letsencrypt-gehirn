#!/bin/php
<?php
//
//  Let's Encrypt hook script for Gehirn DNS Web Service API
//
//  必要なもの:
//    1. ルート権限
//    2. php 5.6 またはそれ以上
//    3. dehydrated (letsencrypt.sh)
//    4. Gehirn DNS Web Service API Wrapper Class
//    5. PHPMailer 6.x Library
//

// Gehirn DNS Web Service API Wrapper Class
require_once("lib/gehirnDNS.php");

// PHPMailer 6.x Library
require_once("lib/PHPMailer/src/PHPMailer.php");
require_once("lib/PHPMailer/src/Exception.php");
require_once("lib/PHPMailer/src/OAuth.php");
require_once("lib/PHPMailer/src/SMTP.php");

use PHPMailer\PHPMailer;

define("TXT_NAME",   "_acme-challenge");
//define("START_WEB",  "/etc/init.d/apache2 start");                // Debian or Ubuntu
//define("STOP_WEB",   "/etc/init.d/apache2 stop");                 // Debian or Ubuntu
define("START_WEB",  "synoservicecfg --start nginx");               // Synology NAS DSM 6.x
define("STOP_WEB",   "synoservicecfg --stop nginx");                // Synology NAS DSM 6.x
define("CERT_DIR",   "/usr/syno/etc/certificate/system/default");   // SSL証明書の保存先
define("GMAIL_USER", "nobody@gmail.com");
define("GMAIL_PASS", "gmail password");
define("SEND_TO",    "nobody@gmail.com");

if ($argc <= 1) die("Usage: ./letsencrypt-gehirn.php <operation> <arg1> <arg2> <arg3> <arg4> <arg5> <arg6> <arg7>\n");

switch ($argv[1]) {
    case "deploy_challenge":
        deployChallenge($argv[2], $argv[3], $argv[4]);
        break;
    case "clean_challenge":
        cleanChallenge($argv[2], $argv[3], $argv[4]);
        break;
    case "deploy_cert":
        deployCert($argv[2], $argv[3], $argv[4], $argv[5], $argv[6], $argv[7]);
        system("synoservicecfg --restart nginx");
        break;
    case "unchanged_cert":
        unchangedCert($argv[2], $argv[3], $argv[4], $argv[5], $argv[6]);
        break;
    case "invalid_challenge":
        invalidChallenge($argv[2], $argv[3]);
        break;
    case "request_failure":
        requestFailure($argv[2], $argv[3], $argv[4]);
        break;
    case "startup_hook":
        startupHook();
        break;
    case "exit_hook":
        exitHook();
        break;
    default:
        die("not implemented hook[".$argv[1]."]\n");
}
exit();

function getZoneName($s) {
    $word = explode(".", $s);
    $cntWord = count($word);
    if ($cntWord > 2) {
        return $word[$cntWord - 2].".".$word[$cntWord - 1];
    }
    return $s;
}

function deployChallenge($domain, $tokenFileName, $tokenValue) {
    $dns = new GehirnDNS(getZoneName($domain));

    // check acme txt record
    $txt = dns_get_record(TXT_NAME.".".$domain, DNS_TXT);
    if (count($txt)) {
        // edit acme txt record
        $rec = $dns->getTXT(TXT_NAME.".".$domain.".");
        $dns->editTXT($rec["id"], TXT_NAME.".".$domain.".", $tokenValue, 600);
    } else {
        // add acme txt record
        $dns->addTXT(TXT_NAME.".".$domain.".", $tokenValue, 600);
    }

    // wait for it to be reflected in dns
    do {
        echo ".";
        sleep(60);
        $txt = dns_get_record(TXT_NAME.".".$domain, DNS_TXT);
    } while (count($txt) == 0 || $txt[0]["txt"] != $tokenValue);
    echo "\n";
}

function cleanChallenge($domain, $tokenFilename, $tokenValue) {
    $txt = dns_get_record(TXT_NAME.".".$domain, DNS_TXT);
    if (count($txt)) {
        // delete acme record
        $dns = new GehirnDNS(getZoneName($domain));
        $dns->deleteTXT(TXT_NAME.".".$domain.".");
        // wait for it to be reflected in dns
        do {
            sleep(60);
            $txt = dns_get_record(TXT_NAME.".".$domain, DNS_TXT);
        } while (count($txt));
    }
}

function deployCert($domain, $keyFile, $certFile, $fullChainFile, $chainFile, $timeStamp) {
    // stop web service
    system(STOP_WEB);

    // backup file
    @rename(CERT_DIR."/privkey.pem",   CERT_DIR."/privkey.pem.old");
    @rename(CERT_DIR."/cert.pem",      CERT_DIR."/cert.pem.old");
    @rename(CERT_DIR."/fullchain.pem", CERT_DIR."/fullchain.pem.old");
    @rename(CERT_DIR."/chain.pem",     CERT_DIR."/chain.pem.old");

    // copy file
    @copy($keyFile,       CERT_DIR."/privkey.pem");
    @copy($certFile,      CERT_DIR."/cert.pem");
    @copy($fullChainFile, CERT_DIR."/fullchain.pem");
    @copy($chainFile,     CERT_DIR."/chain.pem");

    // start web service
    system(START_WEB);

    // Gmail Send
    $title = "Let's Encrypt Message";
    $message = "SSL certificate of $domain created.\n";
    gmail($title, $message, SEND_TO);
}

function unchangedCert($domain, $keyFile, $certFile, $fullChainFile, $chainFile) {
}

function invalidChallenge($domain, $response) {
    // Gmail Send
    $title    = "Let's Encrypt Message";
    $message  = "invalid challenge:\n";
    $message .= "zone name = $domain\n";
    $message .= " response = $response";
    gmail($title, $message, SEND_TO);
}

function requestFailure($statusCode, $reason, $reqType) {
    // Gmail Send
    $title    = "Let's Encrypt Message";
    $message  = "request failure:";
    $message .= "  code = $statusCode\n";
    $message .= "reason = $reason\n";
    $message .= "  type = $reqType";
    gmail($title, $message, SEND_TO);
}

function startupHook() {
}

function exitHook() {
}

function gmail($subject, $body, $to) {
    mb_internal_encoding("UTF-8");
    $mail = new PHPMailer\PHPMailer();
    $mail->isSMTP();
    $mail->SMTPDebug = 1;
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Port = 587;
    $mail->SMTPSecure = 'tls';
    $mail->Username = GMAIL_USER;
    $mail->Password = GMAIL_PASS;
    $mail->CharSet = 'utf-8';
    $mail->Encoding = 'base64';
    $mail->setFrom(GMAIL_USER);
    $mail->addReplyTo(GMAIL_USER);
    $mail->addAddress($to);
    $mail->Subject = $subject;
    $mail->Body = $body;
    $rc = $mail->send();
    return $rc;
}
?>
