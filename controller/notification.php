<?php
/**
 * Created by PhpStorm.
 * User: alexstreeten
 * Date: 8/2/16
 * Time: 12:45 PM
 */

function getNotifications($req, $res) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $query = $db->prepare('select * from user_notification
                                where owner_id = :user_id
                                order by created desc');
        $query->bindParam(':user_id', $user_id);
        if($query->execute()) {
            $notifications = $query->fetchAll(PDO::FETCH_ASSOC);

            $notification_infos = [];
            foreach ($notifications as $notification) {
                $user = json_decode(\Httpful\Request::get(WEB_SERVER . 'users/' . $notification['user_id'])->send(), true);
                if($user) {
                    $notification['User'] = $user['User'];
                    array_push($notification_infos, $notification);
                }
            }

            $newRes = makeResultResponseWithObject($res, 200, $notification_infos);
        } else {
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    } else {
        $newRes = makeResultResponseWithString($res, 401, 'Your token has expired. Please login again.');
    }

    return $newRes;
}

function markNotificationsAsRead($req, $res) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $query = $db->prepare('update user_notification set is_read = 1 where owner_id = :owner_id and is_read = 0');
        $query->bindParam(':owner_id', $user_id);
        if($query->execute()) {
            $newRes = makeResultResponseWithString($res, 200, 'Mark all notifications as read');
        } else {
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    } else {
        $newRes = makeResultResponseWithString($res, 401, 'Your token has expired. Please login again.');
    }

    return $newRes;
}

function clearNotifications($req, $res) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $query = $db->prepare('delete from user_notification where owner_id = :user_id');
        $query->bindParam(':user_id', $user_id);
        if($query->execute()) {
            $newRes = makeResultResponseWithString($res, 200, 'Clear all your notifications');
        } else {
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    } else {
        $newRes = makeResultResponseWithString($res, 401, 'Your token has expired. Please login again.');
    }

    return $newRes;
}

function getNotificationWithId($req, $res, $args = []) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $query = $db->prepare('select * from user_notification 
                                where notification_id = :notification_id');
        $query->bindParam(':notification_id', $args['id']);
        if($query->execute()) {
            $notification = $query->fetch(PDO::FETCH_NAMED);
            $user = json_decode(\Httpful\Request::get(WEB_SERVER . 'users/' . $notification['user_id'])->send(), true);
            if($user) {
                $notification['User'] = $user['User'];
                $newRes = makeResultResponseWithObject($res, 200, $notification);
            } else {
                $newRes = makeResultResponseWithString($res, 400, 'This notification is invalid');
            }
        } else {
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    } else {
        $newRes = makeResultResponseWithString($res, 401, 'Your token has expired. Please login again.');
    }

    return $newRes;
}

function deleteNotificationWithId($req, $res, $args = []) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $query = $db->prepare('delete from user_notification where notification_id = :notification_id');
        $query->bindParam(':notification_id', $args['id']);
        if($query->execute()) {
            $newRes = makeResultResponseWithString($res, 200, 'Removed your notification');
        } else {
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    } else {
        $newRes = makeResultResponseWithString($res, 401, 'Your token has expired. Please login again.');
    }

    return $newRes;
}