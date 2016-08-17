<?php

/**
 * Created by PhpStorm.
 * User: jhpassion0621
 * Date: 11/16/14
 * Time: 9:34 AM
 */

define('PROFCEE_PUSH_TYPE_AGREE_TREND',         0);
define('PROFCEE_PUSH_TYPE_SHARE_TREND',         1);
define('PROFCEE_PUSH_TYPE_REPORT_TREND',        2);
define('PROFCEE_PUSH_TYPE_SEND_MESSAGE',        3);
define('PROFCEE_PUSH_TYPE_BLOCK_CONVERSATION',  4);
define('PROFCEE_PUSH_TYPE_TEST',                5);

function sendNotification($receiver_id, $msg, $noti_id, $noti_type) {

    global $db;
    $query = $db->prepare('select * from devices where user_id = :receiver_id');
    $query->bindParam(':receiver_id', $receiver_id);
    if($query->execute()) {
        $device = $query->fetch(PDO::FETCH_NAMED);

        $deviceToken = $device['device_uid'];
        if (!empty($deviceToken)) {
            $query = $db->prepare('select * from user_notification where owner_id = :receiver_id and is_read = 0');
            $query->bindParam(':receiver_id', $receiver_id);
            if ($query->execute()) {
                $new_notis = $query->fetchAll(PDO::FETCH_ASSOC);

                if ($device['type'] == DEVICE_TYPE_IOS) {
                    sendNotificationToiOSAdHoc($deviceToken, $msg, count($new_notis), $noti_id, $noti_type);
                    sendNotificationToiOSDev($deviceToken, $msg, count($new_notis), $noti_id, $noti_type);
                } else {
                    sendNotificationToAndroid($deviceToken, $msg, count($new_notis), $noti_id, $noti_type);
                }
            }
        }
    }
}

/**
 * @param $deviceToken
 * @param $msg
 * @param $badge
 * @return string
 */
function sendNotificationToiOSAdHoc($deviceToken, $msg, $badge, $noti_id, $noti_type)
{
	$apnsHost      = 'gateway.push.apple.com';

	$apnsPort      = 2195;

	$apnsCert      = 'config/DistributionAPNcert.pem';

	$streamContext = stream_context_create();
    stream_context_set_option($streamContext, 'ssl', 'passphrase', '123456789');
	stream_context_set_option($streamContext, 'ssl', 'local_cert', $apnsCert);

	$apns = stream_socket_client('ssl://' . $apnsHost . ':' . $apnsPort, $error, $errorString, 60, STREAM_CLIENT_CONNECT, $streamContext);

	if (!$apns)

		return ("Failed to connect: " . __LINE__.$error . $errorString . "<br>");

	$payload['aps'] = array(

		'alert' => $msg,

		'badge' => (int) $badge,

		'sound' => 'default',

        'noti_id' => $noti_id,

        'noti_type' => $noti_type

	);

	$payload        = json_encode($payload);

	$apnsMessage    = chr(0) . chr(0) . chr(32) . pack('H*', str_replace(' ', '', $deviceToken)) . chr(0) . chr(strlen($payload)) . $payload;

	$result         = fwrite($apns, $apnsMessage, strlen($apnsMessage));

    fclose($apns);

	if (!$result)
		return ('Message not delivered');
	else
		return ('Message successfully delivered');

}

function sendNotificationToiOSDev($deviceToken, $msg, $badge, $noti_id, $noti_type)
{
    $apnsHost      = 'gateway.sandbox.push.apple.com';

    $apnsPort      = 2195;

    $apnsCert      = 'config/DevelopmentAPNcert.pem';

    $streamContext = stream_context_create();
    stream_context_set_option($streamContext, 'ssl', 'passphrase', '123456789');
    stream_context_set_option($streamContext, 'ssl', 'local_cert', $apnsCert);

    $apns = stream_socket_client('ssl://' . $apnsHost . ':' . $apnsPort, $error, $errorString, 60, STREAM_CLIENT_CONNECT, $streamContext);

    if (!$apns)

        return ("Failed to connect: " . __LINE__.$error . $errorString . "<br>");

    $payload['aps'] = array(

        'alert' => $msg,

        'badge' => (int) $badge,

        'sound' => 'default',

        'noti_id' => $noti_id,

        'noti_type' => $noti_type

    );

    $payload        = json_encode($payload);

    $apnsMessage    = chr(0) . chr(0) . chr(32) . pack('H*', str_replace(' ', '', $deviceToken)) . chr(0) . chr(strlen($payload)) . $payload;

    $result         = fwrite($apns, $apnsMessage, strlen($apnsMessage));

    fclose($apns);

    if (!$result)
        return ('Message not delivered');
    else
        return ('Message successfully delivered');

}

function sendNotificationToAndroid($deviceToken, $msg, $badge, $noti_id, $noti_type) {
    // Set POST variables
    $url = 'https://android.googleapis.com/gcm/send';

    $data = array(
        'alert' => $msg,

        'badge' => (int) $badge,

        'noti_id' => $noti_id,

        'noti_type' => $noti_type
    );
    $registration_ids = array($deviceToken);
    $fields = array(
        'registration_ids' => $registration_ids,
        'data' => $data
    );

    $headers = array(
        'Authorization: key=' . GOOGLE_API_KEY,
        'Content-Type: application/json'
    );
    // Open connection
    $ch = curl_init();

    // Set the url, number of POST vars, POST data
    curl_setopt($ch, CURLOPT_URL, $url);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Disabling SSL Certificate support temporarly
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

    // Execute post
    $result = curl_exec($ch);
    if ($result === FALSE) {
        die('Curl failed: ' . curl_error($ch));
    }

    // Close connection
    curl_close($ch);
}

function sendEmail($title, $message, $toEmail)
{
	try{
        # Instantiate the client.
        $client = new \Http\Adapter\Guzzle6\Client();
        $mgClient = new Mailgun\Mailgun('key-e4efffdf3104d4640ae5ddb8d16637f3', $client);
        $domain = "mailgun.profcee.com";

        # Make the call to the client.
        $mgClient->sendMessage($domain, array(
            'from'    => 'ProFcee <no-reply@profcee.com>',
            'to'      => $toEmail,
            'subject' => $title,
            'html'    => $message
        ));
    } catch (Exception $e) {
        die($e->getMessage());
    }
}

function generateRandomString($length = 10, $type = 0)
{
    if($type == 0) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    } else if($type == 1) {
        $characters = '0123456789';
    } else {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    }
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function makeResultResponseWithObject($res, $code, $messages) {
    $newRes = $res->withStatus($code)
        ->withHeader('Content-Type', 'application/json;charset=utf-8')
        ->write(json_encode($messages));

    return $newRes;
}

function makeResultResponseWithString($res, $code, $message) {
    $result['message'] = $message;
    $newRes = $res->withStatus($code)
        ->withHeader('Content-Type', 'application/json;charset=utf-8')
        ->write(json_encode($result));

    return $newRes;
}

function validateUserAuthentication($req)
{
    global $db;

    $isResult = false;

    $access_token = $req->getHeaderLine(HTTP_HEADER_ACCESS_TOKEN);
    $query = $db->prepare('select * from tokens where token_key = HEX(AES_ENCRYPT(:token_key, \'' . DB_USER_PASSWORD . '\')) and token_expire_at > now()');
    $query->bindParam(':token_key', $access_token);
    if ($query->execute()) {
        $user_access_token = $query->fetch(PDO::FETCH_NAMED);
        if ($user_access_token) {
            $query = $db->prepare('update tokens set token_expire_at = adddate(now(), INTERVAL 1 MONTH) where token_id = :token_id');
            $query->bindParam(':token_id', $user_access_token['token_id']);
            if ($query->execute()) {
                $isResult = $user_access_token['token_user_id'];
            }
        }
    }

    return $isResult;
}

function getFormattedDateString($created) {
    $date = strtotime($created);
    $weekday = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    return $weekday[date('w', $date)] . ', ' . date('M d', $date);
}