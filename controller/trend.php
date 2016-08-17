<?php
/**
 * Created by PhpStorm.
 * User: alexstreeten
 * Date: 7/29/16
 * Time: 3:24 AM
 */

function createNewTrend($req, $res) {
    global  $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $user = json_decode(\Httpful\Request::get(WEB_SERVER . 'users/' . $user_id)->send(), true);

        if($user == false) {
            $newRes = makeResultResponseWithString($res, 403, 'This user is invalid in this app');
        } else if($user['User']['suspend'] == 1) {
            $newRes = makeResultResponseWithString($res, 403, 'You were suspended by Admin');
        } else if($user['User']['active'] == 0) {
            $newRes = makeResultResponseWithString($res, 403, 'Your account is not active.');
        } else {
            $files = $req->getUploadedFiles();
            $params = $req->getParams();

            if (isset($files['trend'])) {
                $trend_image = 'Trend_' . generateRandomString(40) . '.jpeg';
                $files['trend']->moveTo('assets/trend/' . $trend_image);
            }

            $user_location = $user['City']['name'] . ', ' . $user['City']['State']['Country']['name'];
            $query = $db->prepare('insert into trends (user_id,
                                                       body,
                                                       image,
                                                       city_id,
                                                       location,
                                                       modified)
                                               values (:user_id,
                                                       :body,
                                                       :image,
                                                       :city_id,
                                                       :location,
                                                       now())');
            $query->bindParam(':user_id', $user_id);
            $query->bindParam(':body', $params['trend_body']);
            $query->bindParam(':image', $trend_image);
            $query->bindParam(':city_id', $user['City']['id']);
            $query->bindParam(':location', $user_location);
            if($query->execute()) {
                $query = $db->prepare('update users set trends = trends + 1 where id = :user_id');
                $query->bindParam(':user_id', $user_id);
                if($query->execute()) {
                    $newRes = makeResultResponseWithString($res, 200, 'Trend was created successfully');
                } else {
                    $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
                }
            } else {
                $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
            }
        }
    } else {
        $newRes = makeResultResponseWithString($res, 401, 'Your token has expired. Please login again.');
    }

    return $newRes;
}

function getTrends($req, $res) {
    global $db;

    $query = $db->prepare('select trends.* from trends inner join users on trends.user_id = users.id 
                                    where trends.deleted = 0 and trends.pick = 1 and users.deactivate = 0 and users.suspend = 0
                                    order by trends.created desc
                                    limit 5');
    if($query -> execute()) {
        $trends = $query->fetchAll(PDO::FETCH_ASSOC);

        $trend_infos = [];
        foreach ($trends as $trend) {
            $user = json_decode(\Httpful\Request::get(WEB_SERVER . 'users/' . $trend['user_id'])->send(), true);
            if($user == false) {
                continue;
            }

            $trend_info = ['Trend' => $trend, 'User' => $user['User']];

            array_push($trend_infos, $trend_info);
        }

        $newRes = makeResultResponseWithObject($res, 200, $trend_infos);
    } else {
        $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
    }

    return $newRes;
}

function getGuestTopRatedTrends($req, $res) {
    global $db;

    $query = $db->prepare('select trends.* from trends inner join users on trends.user_id = users.id 
                                    where trends.deleted = 0 and users.deactivate = 0 and users.suspend = 0 and (trends.toprated = true or trends.agrees > 29)
                                    order by trends.agrees desc
                                    limit 10');
    if($query->execute()) {
        $trends = $query->fetchAll(PDO::FETCH_ASSOC);

        $trend_infos = [];
        foreach ($trends as $trend) {
            $user = json_decode(\Httpful\Request::get(WEB_SERVER . 'users/' . $trend['user_id'])->send(), true);
            if($user == false) {
                continue;
            }

            $trend_info = ['Trend' => $trend, 'User' => $user['User']];

            array_push($trend_infos, $trend_info);
        }

        $newRes = makeResultResponseWithObject($res, 200, $trend_infos);
    } else {
        $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
    }

    return $newRes;
}

function getUserPicks($req, $res) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $query = $db->prepare('select trends.* from trends inner join users on trends.user_id = users.id 
                                    where trends.deleted = 0 and trends.pick = 1 and users.deactivate = 0 and users.suspend = 0
                                    order by trends.created desc
                                    limit 30');
        if($query -> execute()) {
            $trends = $query->fetchAll(PDO::FETCH_ASSOC);

            $trend_infos = [];
            foreach ($trends as $trend) {
                $user = json_decode(\Httpful\Request::get(WEB_SERVER . 'users/' . $trend['user_id'])->send(), true);
                if($user == false) {
                    continue;
                }

                // agreed
                $query = $db->prepare('select * from agrees 
                                                where trend_id = :trend_id and user_id = :user_id');
                $query->bindParam(':trend_id', $trend['id']);
                $query->bindParam(':user_id', $user_id);
                if($query->execute()) {
                    $agrees = $query->fetchAll(PDO::FETCH_ASSOC);
                    if(count($agrees) > 0) {
                        $trend['agreed'] = true;
                    } else {
                        $trend['agreed'] = false;
                    }
                }

                // abuse
                $query = $db->prepare('select * from abuses 
                                                where trend_id = :trend_id and user_id = :user_id');
                $query->bindParam(':trend_id', $trend['id']);
                $query->bindParam(':user_id', $user_id);
                if($query->execute()) {
                    $abuses = $query->fetchAll(PDO::FETCH_ASSOC);
                    if(count($abuses) > 0) {
                        $trend['abused'] = true;
                    } else {
                        $trend['abused'] = false;
                    }
                }

                $trend_info = ['Trend' => $trend, 'User' => $user['User']];

                array_push($trend_infos, $trend_info);
            }

            $newRes = makeResultResponseWithObject($res, 200, $trend_infos);
        } else {
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    } else {
        $newRes = makeResultResponseWithString($res, 401, 'Your token has expired. Please login again.');
    }

    return $newRes;
}

function getTopRatedTrends($req, $res) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $query = $db->prepare('select trends.* from trends inner join users on trends.user_id = users.id 
                                    where trends.deleted = 0 and users.deactivate = 0 and users.suspend = 0 and (trends.toprated = true or trends.agrees > 29)
                                    order by trends.agrees desc
                                    limit 10');
        if($query -> execute()) {
            $trends = $query->fetchAll(PDO::FETCH_ASSOC);

            $trend_infos = [];
            foreach ($trends as $trend) {
                $user = json_decode(\Httpful\Request::get(WEB_SERVER . 'users/' . $trend['user_id'])->send(), true);

                // agreed
                $query = $db->prepare('select * from agrees 
                                                where trend_id = :trend_id and user_id = :user_id');
                $query->bindParam(':trend_id', $trend['id']);
                $query->bindParam(':user_id', $user_id);
                if($query->execute()) {
                    $agrees = $query->fetchAll(PDO::FETCH_ASSOC);
                    if(count($agrees) > 0) {
                        $trend['agreed'] = true;
                    } else {
                        $trend['agreed'] = false;
                    }
                }

                // abuse
                $query = $db->prepare('select * from abuses 
                                                where trend_id = :trend_id and user_id = :user_id');
                $query->bindParam(':trend_id', $trend['id']);
                $query->bindParam(':user_id', $user_id);
                if($query->execute()) {
                    $abuses = $query->fetchAll(PDO::FETCH_ASSOC);
                    if(count($abuses) > 0) {
                        $trend['abused'] = true;
                    } else {
                        $trend['abused'] = false;
                    }
                }

                $trend_info = ['Trend' => $trend, 'User' => $user['User']];

                array_push($trend_infos, $trend_info);
            }

            $newRes = makeResultResponseWithObject($res, 200, $trend_infos);
        } else {
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    } else {
        $newRes = makeResultResponseWithString($res, 401, 'Your token has expired. Please login again.');
    }

    return $newRes;
}

function getTrendById($req, $res, $args = []) {
    global $db;

    $query = $db->prepare('select * from trends where deleted = 0 and id = :trend_id');
    $query->bindParam(':trend_id', $args['id']);
    if($query->execute()) {
        $trend = $query->fetch(PDO::FETCH_NAMED);

        $newRes = makeResultResponseWithObject($res, 200, $trend);
    } else {
        $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
    }

    return $newRes;
}

function agreeTrend($req, $res, $args = []) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $query = $db->prepare('insert into agrees (user_id,
                                                   trend_id,
                                                   modified)
                                            values (:user_id,
                                                    :trend_id,
                                                    now())');
        $query->bindParam(':user_id', $user_id);
        $query->bindParam(':trend_id', $args['id']);
        if($query -> execute()) {
            $query = $db->prepare('select trends.*, users.email from trends inner join users on trends.user_id = users.id where trends.id = :trend_id');
            $query->bindParam(':trend_id', $args['id']);
            if($query->execute()) {
                $trend = $query->fetch(PDO::FETCH_NAMED);
                $trend['agrees'] += 1;

                // user settings
                $query = $db->prepare('select * from user_settings where user_id = :user_id');
                $query->bindParam(':user_id', $trend['user_id']);
                if($query->execute()) {
                    $owner_setting = $query->fetch(PDO::FETCH_NAMED);

                    if($owner_setting['agree_post']) {
                        $user = json_decode(\Httpful\Request::get(WEB_SERVER . 'users/' . $user_id)->send(), true);
                        $user_location = $user['City']['name'] . ', ' . $user['City']['State']['Country']['name'];

                        // send push notification
                        $push_text = $user['User']['name'] . ' agreed your Trend. Your Trend may become a Prophecy soon!';
                        $noti_type = PROFCEE_PUSH_TYPE_AGREE_TREND;

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
                        $query->bindParam(':owner_id', $trend['user_id']);
                        $query->bindParam(':user_id', $user_id);
                        $query->bindParam(':object_id', $trend['id']);
                        $query->bindParam(':notification_text', $push_text);
                        if($query->execute()) {
                            sendNotification($trend['user_id'], $push_text, $db->lastInsertId(), $noti_type);
                        }

                        // send email
                        $email_text = 'Hi there,<br><br>' . ' Just want to delight you with a news.<br><br>' .
                                      'You posted "' . $trend['body'] . '" on ' . getFormattedDateString($trend['created']) . '.<br><br>' .
                                      $user['User']['name'] . ' from ' . $user_location . ' agreed to your Post.<br><br>' .
                                      'It is a validation of your observation. <br>' .
                                      'Your observation is on the way to become a prophecy. You may like to share and promote it more with other people.<br><br>' .
                                      'Now you can chat with ' . $user['User']['name'] . ' within the app. Just tap on the number of agrees to see the list of the users who agree to your post.<br><br>' .
                                      'You are becoming popular! <br><br>' .
                                      'ProFcee Team';
                        sendEmail('Someone agreed to your post', $email_text, $trend['email']);
                    }

                    if($trend['agrees'] > 29 &&
                       $trend['toprated'] == 0) {

                        // send email
                        $email_text = 'Hi there, <br><br>' .
                                      'You posted "' . $trend['body'] . '" on ' . getFormattedDateString($trend['created']) . '.<br><br>' .
                                      'We are delighted to inform you that your observation has become a prophecy now! <br><br>' .
                                      'You have created history!<br><br> ' .
                                      'View your prophecy in the app now.<br><br>' .
                                      'Now it is our duty to print it in our annual ProFcee Book.<br>' .
                                      'Hope you would like to keep a copy of the book for yourself and your loved ones!<br><br>' .
                                      'You are becoming popular!<br><br>' .
                                      'ProFcee Team';
                        sendEmail('It\'s a great day for you. You created History!', $email_text, $trend['email']);
                    }

                    $query = $db->prepare('update trends set agrees = :trend_agrees,
                                                             toprated = :trend_toprated
                                                       where id = :trend_id');
                    $query->bindParam(':trend_agrees', $trend['agrees']);
                    $query->bindParam(':trend_toprated', $trend['toprated']);
                    $query->bindParam(':trend_id', $trend['id']);
                    if($query->execute()) {
                        $newRes = makeResultResponseWithString($res, 200, 'Agreed Trend Successfully');
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
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    } else {
        $newRes = makeResultResponseWithString($res, 401, 'Your token has expired. Please login again.');
    }

    return $newRes;
}

function shareTrend($req, $res, $args = []) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $query = $db->prepare('select trends.*, users.email from trends inner join users on trends.user_id = users.id where trends.id = :trend_id');
        $query->bindParam(':trend_id', $args['id']);
        if ($query->execute()) {
            $trend = $query->fetch(PDO::FETCH_NAMED);

            // user settings
            $query = $db->prepare('select * from user_settings where user_id = :user_id');
            $query->bindParam(':user_id', $trend['user_id']);
            if ($query->execute()) {
                $owner_setting = $query->fetch(PDO::FETCH_NAMED);
                if ($owner_setting['shares'] && $trend['user_id'] != $user_id) {
                    $user = json_decode(\Httpful\Request::get(WEB_SERVER . 'users/' . $user_id)->send(), true);
                    $user_location = $user['City']['name'] . ', ' . $user['City']['State']['Country']['name'];

                    // send push notification
                    $push_text = $user['User']['name'] . '  shared your Trend with his friends. Your Trend may become a Prophecy soon!';
                    $noti_type = PROFCEE_PUSH_TYPE_SHARE_TREND_TREND;

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
                    $query->bindParam(':owner_id', $trend['user_id']);
                    $query->bindParam(':user_id', $user_id);
                    $query->bindParam(':object_id', $trend['id']);
                    $query->bindParam(':notification_text', $push_text);
                    if($query->execute()) {
                        sendNotification($trend['user_id'], $push_text, $db->lastInsertId(), $noti_type);
                    }

                    // send email
                    $email_text = 'Hi there,<br><br>' . ' Just want to delight you with a news.<br><br>' .
                                  'You posted "' . $trend['body'] . '" on ' . getFormattedDateString($trend['created']) . '.<br><br>' .
                                  $user['User']['name'] . ' from ' . $user_location . ' shared your Trend with his friends.<br><br>' .
                                  'It is a validation of your observation. <br>' .
                                  'Your observation is on the way to become a prophecy. You may like to share and promote it more with other people.<br><br>' .
                                  'You are becoming popular! <br><br>' .
                                  'ProFcee Team';
                    sendEmail('Someone reported your post as abuse', $email_text, $trend['email']);
                }

                $newRes = makeResultResponseWithString($res, 200, 'Share Trend Successfully');
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

function reportTrend($req, $res, $args = []) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $query = $db->prepare('insert into abuses (user_id,
                                                   trend_id)
                                            values (:user_id,
                                                    :trend_id)');
        $query->bindParam(':user_id', $user_id);
        $query->bindParam(':trend_id', $args['id']);
        if($query -> execute()) {
            $query = $db->prepare('select trends.*, users.email from trends inner join users on trends.user_id = users.id where trends.id = :trend_id');
            $query->bindParam(':trend_id', $args['id']);
            if ($query->execute()) {
                $trend = $query->fetch(PDO::FETCH_NAMED);

                // user settings
                $query = $db->prepare('select * from user_settings where user_id = :user_id');
                $query->bindParam(':user_id', $trend['user_id']);
                if ($query->execute()) {
                    $owner_setting = $query->fetch(PDO::FETCH_NAMED);
                    if ($owner_setting['reports']) {
                        $user = json_decode(\Httpful\Request::get(WEB_SERVER . 'users/' . $user_id)->send(), true);
                        $user_location = $user['City']['name'] . ', ' . $user['City']['State']['Country']['name'];

                        // send push notification
                        $push_text = $user['User']['name'] . ' reported your Trend as abuse. We will verify if reporting is spurious.';
                        $noti_type = PROFCEE_PUSH_TYPE_REPORT_TREND;

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
                        $query->bindParam(':owner_id', $trend['user_id']);
                        $query->bindParam(':user_id', $user_id);
                        $query->bindParam(':object_id', $trend['id']);
                        $query->bindParam(':notification_text', $push_text);
                        if($query->execute()) {
                            sendNotification($trend['user_id'], $push_text, $db->lastInsertId(), $noti_type);
                        }

                        // send email
                        $email_text = 'Hi there,<br><br>' .
                                      'You posted "' . $trend['body'] . '" on ' . getFormattedDateString($trend['created']) . '.<br><br>' .
                                      $user['User']['name'] . ' from ' . $user_location . ' reported this post as abuse.<br><br>' .
                                      'We will inspect the post to ensure that there is no mischievous reporting.<br><br>' .
                                      'ProFcee Team';
                        sendEmail('Someone reported your post as abuse', $email_text, $trend['email']);
                    }

                    $newRes = makeResultResponseWithString($res, 200, 'Trend Reported Successfully');
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

function searchTrend($req, $res) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $params = $req->getParams();
        if($params['type'] == SEARCH_TREND_BY_KEYWORD) {
            $sql = "select * from trends where deleted = 0 and LOWER(body) LIKE '%" . $params['keyword'] . "%'";
        } else {
            $sql = "select * from trends where deleted = 0 and LOWER(location) LIKE '%" . $params['keyword'] . "%'";
        }

        $query = $db->prepare($sql);
        if($query->execute()) {
            $trends = $query->fetchAll(PDO::FETCH_ASSOC);

            $trend_infos = [];
            foreach ($trends as $trend) {
                $user = json_decode(\Httpful\Request::get(WEB_SERVER . 'users/' . $trend['user_id'])->send(), true);
                if($user == false) {
                    continue;
                }

                // agreed
                $query = $db->prepare('select * from agrees 
                                                where trend_id = :trend_id and user_id = :user_id');
                $query->bindParam(':trend_id', $trend['id']);
                $query->bindParam(':user_id', $user_id);
                if($query->execute()) {
                    $agrees = $query->fetchAll(PDO::FETCH_ASSOC);
                    if(count($agrees) > 0) {
                        $trend['agreed'] = true;
                    } else {
                        $trend['agreed'] = false;
                    }
                }

                // abuse
                $query = $db->prepare('select * from abuses 
                                                where trend_id = :trend_id and user_id = :user_id');
                $query->bindParam(':trend_id', $trend['id']);
                $query->bindParam(':user_id', $user_id);
                if($query->execute()) {
                    $abuses = $query->fetchAll(PDO::FETCH_ASSOC);
                    if(count($abuses) > 0) {
                        $trend['abused'] = true;
                    } else {
                        $trend['abused'] = false;
                    }
                }

                $trend_info = ['Trend' => $trend, 'User' => $user['User']];

                array_push($trend_infos, $trend_info);
            }

            $newRes = makeResultResponseWithObject($res, 200, $trend_infos);
        } else {
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    } else {
        $newRes = makeResultResponseWithString($res, 401, 'Your token has expired. Please login again.');
    }

    return $newRes;
}

function trendAgrees($req, $res, $args = []) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $query = $db->prepare('select user_id from agrees where trend_id = :trend_id');
        $query->bindParam(':trend_id', $args['id']);
        if($query->execute()) {
            $user_ids = $query->fetchAll(PDO::FETCH_ASSOC);

            $user_infos = [];
            foreach ($user_ids as $agree_id) {
                $user = json_decode(\Httpful\Request::get(WEB_SERVER . 'users/' . $agree_id['user_id'])->send(), true);
                if($user == false) {
                    continue;
                }

                array_push($user_infos, $user);
            }

            $newRes = makeResultResponseWithObject($res, 200, $user_infos);
        } else {
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    } else {
        $newRes = makeResultResponseWithString($res, 401, 'Your token has expired. Please login again.');
    }

    return $newRes;
}

function deleteTrend($req, $res, $args = []) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $query = $db->prepare('update trends set deleted = 1 where id = :trend_id');
        $query->bindParam(':trend_id', $args['id']);
        if($query->execute()) {
            $query = $db->prepare('update users set trends = trends - 1 where id = :user_id');
            $query->bindParam(':user_id', $user_id);

            if($query->execute()) {
                $query = $db->prepare('select * from trends where id = :trend_id');
                $query->bindParam(':trend_id', $args['id']);
                if($query->execute()) {
                    $trend = $query->fetch(PDO::FETCH_NAMED);
                    if(isset($trend['image'])) {
                        unlink('assets/trend/' . $trend['image']);
                    }
                }

                //remove all trends
                $query = $db->prepare('delete from replies where conversaion_id in (select conversation_id from conversations where trend_id = :trend_id)');
                $query->bindParam(':trend_id', $args['id']);
                $query->execute();

                $query = $db->prepare('delete from conversations where trend_id = :trend_id ');
                $query->bindParam(':trend_id', $args['id']);
                $query->execute();

                $query = $db->prepare('delete from agrees where trend_id = :trend_id ');
                $query->bindParam(':trend_id', $args['id']);
                $query->execute();

                $query = $db->prepare('delete from abuses where trend_id = :trend_id ');
                $query->bindParam(':trend_id', $args['id']);
                $query->execute();

                $newRes = makeResultResponseWithString($res, 200, 'Removed your trend successfully');
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