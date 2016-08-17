<?php
/**
 * Created by PhpStorm.
 * User: alexstreeten
 * Date: 7/29/16
 * Time: 1:07 PM
 */

function getStates($req, $res) {
    global $db;

    $params = $req->getParams();
    $query = $db->prepare('select * from states where country_id = :country_id order by name');
    $query->bindParam(':country_id', $params['country_id']);
    if($query->execute()) {
        $states = $query->fetchAll(PDO::FETCH_ASSOC);
        $newRes = makeResultResponseWithObject($res, 200, $states);
    } else {
        $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
    }

    return $newRes;
}

function getStateWithId($req, $res, $args = []) {
    global $db;

    $query = $db->prepare('select * from states where id = :id');
    $query->bindParam(':id', $args['id']);
    if($query->execute()) {
        $state = $query->fetch(PDO::FETCH_NAMED);
        $newRes = makeResultResponseWithObject($res, 200, $state);
    } else {
        $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
    }

    return $newRes;
}