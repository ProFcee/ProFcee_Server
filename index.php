<?php

// Include the SDK using the Composer autoloader
require 'vendor/autoload.php';

// Includes ;
require_once( 'config/database.php' );
require_once( 'controller/base.php' );

$app = new Slim\App();

$app->group('', function() use ($app){
    $app->group('/users', function() use ($app){
        require_once 'controller/user.php';
        $app->post('', 'signUp');
        $app->post('/auth', 'auth');
        $app->post('/social', 'socialLogin');
        $app->get('/forgot/password', 'forgotPassword');
        $app->patch('/reset/password', 'resetPassword');
        $app->patch('/update/password', 'updatePassword');
        $app->patch('/deactivate', 'deactivateUser');
        $app->post('/update', 'updateUser');
        $app->get('/verify/email', 'verifyEmail');

        $app->group('/{id}', function() use ($app) {
            $app->get('', 'getUser');
            $app->get('/trends', 'getUserTrends');
            $app->get('/agreed/trends', 'getUserAgreedTrends');
            $app->delete('/logout', 'logOut');
        });
    });

    $app->group('/countries', function() use ($app){
        require_once  'controller/country.php';
        $app->get('', 'getCountries');
        $app->get('/{id}', 'getCountryWithId');
    });

    $app->group('/states', function() use ($app){
        require_once  'controller/state.php';
        $app->get('', 'getStates');
        $app->get('/{id}', 'getStateWithId');
    });

    $app->group('/cities', function() use ($app){
        require_once  'controller/city.php';
        $app->get('', 'getCities');
        $app->get('/{id}', 'getCityWithId');
    });

    $app->group('/trends', function() use ($app){
        require_once 'controller/trend.php';
        $app->post('', 'createNewTrend');
        $app->get('', 'getTrends');
        $app->get('/guest/toprated', 'getGuestTopRatedTrends');
        $app->get('/picks', 'getUserPicks');
        $app->get('/toprated', 'getTopRatedTrends');
        $app->group('/{id}', function() use ($app) {
            $app->get('', 'getTrendById');
            $app->delete('', 'deleteTrend');
            $app->patch('/agree', 'agreeTrend');
            $app->patch('/report', 'reportTrend');
            $app->patch('/share', 'shareTrend');
            $app->get('/agrees', 'trendAgrees');
        });
    });

    $app->group('/notifications', function() use ($app){
        require_once 'controller/notification.php';
        $app->get('', 'getNotifications');
        $app->patch('', 'markNotificationsAsRead');
        $app->delete('', 'clearNotifications');
        $app->group('/{id}', function() use ($app) {
            $app->get('', 'getNotificationWithId');
            $app->delete('', 'deleteNotificationWithId');
        });
    });

    $app->group('/settings', function() use ($app){
        require_once 'controller/setting.php';
        $app->get('', 'getSettings');
        $app->put('', 'updateSettings');
    });

    $app->group('/messages', function() use ($app){
        require_once 'controller/message.php';
        $app->post('', 'createChatRoom');
        $app->get('/chatrooms', 'getChatrooms');
        $app->get('/reply/{id}', 'getReplyById');
        $app->delete('/reply/{id}', 'deleteReply');
        $app->patch('/reply/{id}', 'markReplyAsRead');

        $app->group('/{id}', function() use ($app) {
            $app->post('', 'sendReply');
            $app->get('', 'getAllReplies');
            $app->delete('', 'deleteChatroom');
            $app->patch('/block', 'blockChatroom');
        });
    });

    $app->get('/search', 'searchInformation');
    $app->any('/document', 'getAPIDoc');
    $app->any('/push', 'sendPush');
});

$app->run();

function getAPIDoc($req, $res) {
    $strJson = file_get_contents('docs/swagger.json');

    $newRes = $res->withStatus(200)
        ->withHeader('Content-Type', 'application/json;charset=utf-8')
        ->write($strJson);

    return $newRes;
}

function searchInformation($req, $res) {
    $params = $req->getParams();
    if($params['type'] == SEARCH_USER_BY_NAME
    || $params['type'] == SEARCH_USER_BY_EMAIL) {
        require_once 'controller/user.php';
        $newRes = searchUser($req, $res);
    } else {
        require_once 'controller/trend.php';
        $newRes = searchTrend($req, $res);
    }

    return $newRes;
}

function sendPush($req, $res) {
    global $db;

    $params = $req->getParams();

    $noti_type = PROFCEE_PUSH_TYPE_TEST;
    $user_id = '--';
    $object_id = 1;
    $push_text = 'This is test message for push test';

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
    $query->bindParam(':owner_id', $params['user_id']);
    $query->bindParam(':user_id', $user_id);
    $query->bindParam(':object_id', $object_id);
    $query->bindParam(':notification_text', $push_text);
    if($query->execute()) {
        sendNotification($params['user_id'], $push_text, $object_id, $noti_type);
    }

//    return makeResultResponseWithString($res, 200, 'Sent push successfully');
}

