<?php
/**
 * Created by PhpStorm.
 * User: alexstreeten
 * Date: 8/2/16
 * Time: 8:51 PM
 */

function getSettings($req, $res) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $query = $db->prepare('select * from user_settings where user_id = :user_id');
        $query->bindParam(':user_id', $user_id);
        if($query->execute()) {
            $settings = $query->fetch(PDO::FETCH_NAMED);
            $newRes = makeResultResponseWithObject($res, 200, $settings);
        } else {
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    } else {
        $newRes = makeResultResponseWithString($res, 401, 'Your token has expired. Please login again.');
    }

    return $newRes;
}

function updateSettings($req, $res) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $params = $req->getParams();

        $query = $db->prepare('update user_settings set agree_post = :agree_post,
                                                        reports = :reports,
                                                        shares = :shares,
                                                        toprated = :toprated,
                                                        new_products = :new_products,
                                                        participation = :participation,
                                                        interesting = :interesting,
                                                        use_word = :use_word,
                                                        use_spell = :use_spell
                                                  where user_id = :user_id');

        $query->bindParam(':agree_post', $params['agree_post']);
        $query->bindParam(':reports', $params['reports']);
        $query->bindParam(':shares', $params['shares']);
        $query->bindParam(':toprated', $params['toprated']);
        $query->bindParam(':new_products', $params['new_products']);
        $query->bindParam(':participation', $params['participation']);
        $query->bindParam(':interesting', $params['interesting']);
        $query->bindParam(':use_word', $params['use_word']);
        $query->bindParam(':use_spell', $params['use_spell']);
        $query->bindParam(':user_id', $user_id);
        if($query->execute()) {
            $newRes = makeResultResponseWithString($res, 200, 'Updated your settings successfully');
        } else {
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    } else {
        $newRes = makeResultResponseWithString($res, 401, 'Your token has expired. Please login again.');
    }

    return $newRes;
}