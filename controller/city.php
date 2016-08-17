<?php
/**
 * Created by PhpStorm.
 * User: alexstreeten
 * Date: 7/29/16
 * Time: 1:08 PM
 */

function getCities($req, $res) {
    global $db;

    $params = $req->getParams();
    $query = $db->prepare('select * from cities where state_id = :state_id order by name');
    $query->bindParam(':state_id', $params['state_id']);
    if($query->execute()) {
        $cities = $query->fetchAll(PDO::FETCH_ASSOC);
        $newRes = makeResultResponseWithObject($res, 200, $cities);
    } else {
        $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
    }

    return $newRes;
}

function getCityWithId($req, $res, $args = []) {
    global $db;

    $query = $db->prepare('select * from cities where id = :id');
    $query->bindParam(':id', $args['id']);
    if($query->execute()) {
        $city = $query->fetch(PDO::FETCH_NAMED);
        $newRes = makeResultResponseWithObject($res, 200, $city);
    } else {
        $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
    }

    return $newRes;
}