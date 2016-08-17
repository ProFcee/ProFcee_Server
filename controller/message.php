<?php
/**
 * Created by PhpStorm.
 * User: alexstreeten
 * Date: 8/4/16
 * Time: 10:50 PM
 */

function createChatRoom($req, $res) {
    global $db;

    $owner_id = validateUserAuthentication($req);
    if($owner_id) {
        $params = $req->getParams();

        $user_ids = json_decode($params['user_ids'], true);
        foreach ($user_ids as $user_id) {
            $query = $db->prepare('select * from conversations where user1 = :user1 and user2 = :user2 and trend_id = :trend_id');
            $query->bindParam(':user1', $owner_id);
            $query->bindParam(':user2', $user_id);
            $query->bindParam(':trend_id', $params['trend_id']);

            if($query->execute()) {
                $result = $query->fetchAll(PDO::FETCH_ASSOC);
                if(count($result) == 0) {
                    // create conversation
                    $query = $db->prepare('insert into conversations (user1,
                                                                      user2,
                                                                      trend_id,
                                                                      modified)
                                                              values (:user1,
                                                                      :user2,
                                                                      :trend_id,
                                                                      now())');
                    $query->bindParam(':user1', $owner_id);
                    $query->bindParam(':user2', $user_id);
                    $query->bindParam(':trend_id', $params['trend_id']);

                    if($query->execute()) {
                        $conversation_id = $db->lastInsertId();

                        // create reply
                        $query = $db->prepare('insert into replies (conversation_id,
                                                            body,
                                                            user_id,
                                                            modified)
                                                    values (:conversation_id,
                                                            :body,
                                                            :user_id,
                                                            now())');
                        $query->bindParam(':conversation_id', $conversation_id);
                        $query->bindParam(':body', $params['message']);
                        $query->bindParam(':user_id', $owner_id);

                        $query->execute();
                    }
                }
            }
        }

        $newRes = makeResultResponseWithString($res, 200, 'Created chatrooms successfully');
    } else {
        $newRes = makeResultResponseWithString($res, 401, 'Your token has expired. Please login again.');
    }

    return $newRes;
}

function getChatrooms($req, $res) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $query = $db->prepare('select * from conversations where (user1 = :user_id or user2 = :user_id) and blocked = 0');
        $query->bindParam(':user_id', $user_id);
        if($query->execute()) {
            $conversations = $query->fetchAll(PDO::FETCH_ASSOC);

            $conversation_infos = [];
            foreach ($conversations as $conversation) {
                $conversation_info['conversation_id'] = $conversation['id'];

                $query = $db->prepare('select * from replies where conversation_id = :conversation_id and user_id <> :user_id and is_read = 0');
                $query->bindParam(':conversation_id', $conversation['id']);
                $query->bindParam(':user_id', $user_id);
                if($query->execute()) {
                    $new_messages = $query->fetchAll(PDO::FETCH_ASSOC);
                    $conversation_info['conversation_new'] = count($new_messages);
                }

                if($conversation['user1'] == $user_id) {
                    $user2_id = $conversation['user2'];
                    $conversation_info['conversation_agreed'] = false;
                } else {
                    $user2_id = $conversation['user1'];
                    $conversation_info['conversation_agreed'] = true;
                }

                $user2 = json_decode(\Httpful\Request::get(WEB_SERVER . 'users/' . $user2_id)->send(), true);
                if($user2 == false) {
                    continue;
                }
                $conversation_info['User'] = $user2;

                $trend = json_decode(\Httpful\Request::get(WEB_SERVER . 'trends/' . $conversation['trend_id'])->send(), true);
                if($trend == false) {
                    continue;
                }
                $conversation_info['Trend'] = $trend;

                array_push($conversation_infos, $conversation_info);
            }

            $newRes = makeResultResponseWithObject($res, 200, $conversation_infos);
        } else {
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    } else {
        $newRes = makeResultResponseWithString($res, 401, 'Your token has expired. Please login again.');
    }

    return $newRes;
}

function sendReply($req, $res, $args = []) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $params = $req->getParams();

        $query = $db->prepare('insert into replies (conversation_id,
                                                    body,
                                                    user_id,
                                                    modified)
                                            values (:conversation_id,
                                                    :body,
                                                    :user_id,
                                                    now())');
        $query->bindParam(':user_id', $user_id);
        $query->bindParam(':body', $params['message']);
        $query->bindParam(':conversation_id', $args['id']);
        if($query->execute()) {
            $reply_id = $db->lastInsertId();

            $query = $db->prepare('select * from conversations where id = :conversation_id');
            $query->bindParam('conversation_id', $args['id']);
            if($query->execute()) {
                $conversation = $query->fetch(PDO::FETCH_NAMED);

                if($conversation['user1'] == $user_id) {
                    $user2 = $conversation['user2'];
                } else {
                    $user2 = $conversation['user1'];
                }

                // send push notification
                $push_text = $params['user_name'] . ' just replied to you on conversation';
                $noti_type = PROFCEE_PUSH_TYPE_SEND_MESSAGE;

                $query = $db->prepare('insert into user_notification (type,
                                                                      owner_id,
                                                                      user_id,
                                                                      object_id,
                                                                      notification_text)
                                                              values (:noti_type,
                                                                      :owner_id,
                                                                      :user_id,
                                                                      :object_id,
                                                                      :notification_text)');

                $query->bindParam(':noti_type', $noti_type);
                $query->bindParam(':owner_id', $user2);
                $query->bindParam(':user_id', $user_id);
                $query->bindParam(':object_id', $reply_id);
                $query->bindParam(':notification_text', $push_text);
                if($query->execute()) {
                    sendNotification($user2, $push_text, $db->lastInsertId(), $noti_type);

                    $newRes = makeResultResponseWithString($res, 200, 'Send your reply successfully');
                } else {
                    $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
                }
            } else {
                $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
            }
        } else {
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    } else {
        $newRes = makeResultResponseWithString($res, 401, 'Your token has expired. Please login again.');
    }

    return $newRes;
}

function getReplyById($req, $res, $args = []) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $query = $db->prepare('select * from replies where id = :reply_id');
        $query->bindParam(':reply_id', $args['id']);
        if($query->execute()) {
            $resply = $query->fetch(PDO::FETCH_NAMED);
            $newRes = makeResultResponseWithObject($res, 200, $resply);
        } else {
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    } else {
        $newRes = makeResultResponseWithString($res, 401, 'Your token has expired. Please login again.');
    }

    return $newRes;
}

function deleteReply($req, $res, $args = []) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $query = $db->prepare('delete from replies where id = :reply_id');
        $query->bindParam(':reply_id', $args['id']);
        if($query->execute()) {
            $newRes = makeResultResponseWithString($res, 200, 'Removed reply successfully');
        } else {
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    } else {
        $newRes = makeResultResponseWithString($res, 401, 'Your token has expired. Please login again.');
    }

    return $newRes;
}

function markReplyAsRead($req, $res, $args = []) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $query = $db->prepare('update replies set is_read = 1
                                            where id = :reply_id');
        $query->bindParam(':reply_id', $args['id']);
        if($query->execute()) {
            $noti_type = PROFCEE_PUSH_TYPE_SEND_MESSAGE;
            $query = $db->prepare('update user_notification set is_read = 1 
                                                          where owner_id = :owner_id 
                                                            and is_read = 0 
                                                            and type = :noti_type
                                                            and object_id = :reply_id');
            $query->bindParam(':owner_id', $user_id);
            $query->bindParam(':noti_type', $noti_type);
            $query->bindParam(':reply_id', $args['id']);

            if($query->execute()) {
                $newRes = makeResultResponseWithString($res, 200, 'Mark reply as read successfully');
            } else {
                $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
            }
        } else {
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    } else {
        $newRes = makeResultResponseWithString($res, 401, 'Your token has expired. Please login again.');
    }

    return $newRes;
}

function getAllReplies($req, $res, $args = []) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $query = $db->prepare('select * from replies where conversation_id = :conversation_id');
        $query->bindParam(':conversation_id', $args['id']);
        if($query->execute()) {
            $messages = $query->fetchAll(PDO::FETCH_ASSOC);

            $query = $db->prepare('update replies set is_read = 1 
                                                where conversation_id = :conversation_id
                                                  and user_id <> :user_id and is_read = 0');
            $query->bindParam(':user_id', $user_id);
            $query->bindParam(':conversation_id', $args['id']);
            if($query->execute()) {
                $noti_type = PROFCEE_PUSH_TYPE_SEND_MESSAGE;
                $query = $db->prepare('update user_notification set is_read = 1 
                                                              where owner_id = :owner_id 
                                                                and is_read = 0 
                                                                and type = :noti_type');
                $query->bindParam(':owner_id', $user_id);
                $query->bindParam(':noti_type', $noti_type);
                if($query->execute()) {
                    $newRes = makeResultResponseWithObject($res, 200, $messages);
                } else {
                    $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
                }
            } else {
                $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
            }
        } else {
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    } else {
        $newRes = makeResultResponseWithString($res, 401, 'Your token has expired. Please login again.');
    }

    return $newRes;
}

function blockChatroom($req, $res, $args = []) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $params = $req->getParams();

        $query = $db->prepare('update conversations set blocked = 1,
                                                        blocked_by = :user_id 
                                                  where id = :conversation_id');
        $query->bindParam(':user_id', $user_id);
        $query->bindParam(':conversation_id', $args['id']);
        if($query->execute()) {

            $query = $db->prepare('select * from conversations where id = :conversation_id');
            $query->bindParam('conversation_id', $args['id']);
            if($query->execute()) {
                $conversation = $query->fetch(PDO::FETCH_NAMED);

                if($conversation['user1'] == $user_id) {
                    $user2 = $conversation['user2'];
                } else {
                    $user2 = $conversation['user1'];
                }

                // send push notification
                $push_text = $params['user_name'] . ' just blocked you on conversation';
                $noti_type = PROFCEE_PUSH_TYPE_BLOCK_CONVERSATION;

                $query = $db->prepare('insert into user_notification (type,
                                                                      owner_id,
                                                                      user_id,
                                                                      object_id,
                                                                      notification_text)
                                                              values (:noti_type,
                                                                      :owner_id,
                                                                      :user_id,
                                                                      :object_id,
                                                                      :notification_text)');

                $query->bindParam(':noti_type', $noti_type);
                $query->bindParam(':owner_id', $user2);
                $query->bindParam(':user_id', $user_id);
                $query->bindParam(':object_id', $conversation['id']);
                $query->bindParam(':notification_text', $push_text);
                if($query->execute()) {
                    sendNotification($user2, $push_text, $db->lastInsertId(), $noti_type);

                    $newRes = makeResultResponseWithString($res, 200, 'Blocked this chat room successfully');
                } else {
                    $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
                }
            } else {
                $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
            }
        } else {
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    } else {
        $newRes = makeResultResponseWithString($res, 401, 'Your token has expired. Please login again.');
    }

    return $newRes;
}

function deleteChatroom($req, $res, $args = []) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $query = $db->prepare('delete from replies where conversation_id = :conversation_id');
        $query->bindParam(':conversation_id', $args['id']);
        if($query->execute()) {
            $query = $db->prepare('delete from conversations where id = :conversation_id');
            $query->bindParam(':conversation_id', $args['id']);
            if($query->execute()) {
                $newRes = makeResultResponseWithString($res, 200, 'Removed your chatroom successfully');
            } else {
                $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
            }
        } else {
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    } else {
        $newRes = makeResultResponseWithString($res, 401, 'Your token has expired. Please login again.');
    }

    return $newRes;
}