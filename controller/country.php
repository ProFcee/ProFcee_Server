<?php
/**
 * Created by PhpStorm.
 * User: alexstreeten
 * Date: 7/29/16
 * Time: 1:07 PM
 */

function getCountries($req, $res) {
    global $db;

    $query = $db->prepare('select * from countries order by name');
    if($query->execute()) {
        $countries = $query->fetchAll(PDO::FETCH_ASSOC);
        $newRes = makeResultResponseWithObject($res, 200, $countries);
    } else {
        $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
    }

    return $newRes;
}

function getCountryWithId($req, $res, $args = []) {
    global $db;

    $query = $db->prepare('select * from countries where id = :id');
    $query->bindParam(':id', $args['id']);
    if($query->execute()) {
        $country = $query->fetch(PDO::FETCH_NAMED);
        $newRes = makeResultResponseWithObject($res, 200, $country);
    } else {
        $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
    }

    return $newRes;
}