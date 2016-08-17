<?php
/**
 * Created by PhpStorm.
 * User: alexstreeten
 * Date: 7/29/16
 * Time: 3:24 AM
 */

function signup($req, $res) {
    global $db;

    $params = $req->getParams();

    $activation_code = generateRandomString(6, 1);

    $query = $db->prepare('insert into users (email,
                                              name,
                                              password,
                                              activation_code,
                                              modified) 
                                      values (:user_email,
                                              :user_name,
                                              HEX(AES_ENCRYPT(:user_pass, \'' . DB_USER_PASSWORD . '\')),
                                              :activation_code,
                                              now())');

    $query->bindParam(':user_email', $params['user_email']);
    $query->bindParam(':user_name', $params['user_name']);
    $query->bindParam(':user_pass', $params['user_pass']);
    $query->bindParam(':activation_code', $activation_code);

    if($query->execute()) {

        $query = $db->prepare('select * from users where id = :user_id');
        $query->bindParam(':user_id', $db->lastInsertId());
        if($query->execute()) {
            $user = $query->fetch(PDO::FETCH_NAMED);

            // register user settings
            $query = $db->prepare('insert into user_settings (user_id)
                                                      values (:user_id)');
            $query->bindParam(':user_id', $user['id']);
            if($query->execute()) {
                // register user device
                if($params['user_device_token'] != '') {

                    $query = $db->prepare('insert into devices (device_uid,
                                                                type,
                                                                user_id,
                                                                modified,
                                                                lastused)
                                                        values (:user_device_token,
                                                                :user_device_type,
                                                                :user_id,
                                                                now(),
                                                                now())');
                    $query->bindParam(':user_device_token', $params['user_device_token']);
                    $query->bindParam(':user_device_type', $params['user_device_type']);
                    $query->bindParam(':user_id', $user['id']);

                    $query->execute();
                }

                $link = "http://104.154.77.58/api/v2/users/verify/email";

                // send email
                $email_text = 'Welcome to ' . '<b>ProFcee</b>. <br>' . '
                               To confirm your account, Please open your mail on your device and click this <a href =' . $link . '?code=' . $activation_code . '>link</>.';
                sendEmail('Welcome to ProFcee', $email_text, $params['user_email']);

                $me = getUserInformation($user);
                $me['access_token'] = createUserAccessToken($user['id'], $user['email']);

                $newRes = makeResultResponseWithObject($res, 200, $me);

            } else {
                $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
            }

        } else {
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    } else {
        if($query->errorInfo()[1] == 1062) {
            $newRes = makeResultResponseWithString($res, 400, 'This email is already used in ProFcee');
        } else {
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    }

    return $newRes;
}

function auth($req, $res) {
    global $db;

    $params = $req->getParams();

    $query = $db->prepare('select * from users where
                            (email = :user_email and password = HEX(AES_ENCRYPT(:user_pass, \'' . DB_USER_PASSWORD . '\')))');
    $query->bindParam(':user_email', $params['user_email']);
    $query->bindParam(':user_pass', $params['user_pass']);
    $query->execute();
    $user = $query->fetch(PDO::FETCH_NAMED);

    if($user) {
        if($user['deactivate'] == 1) {
            $query = $db->prepare('update users set deactivate = 0 where id = :user_id');
            $query->bindParam(':user_id', $user['id']);
            $query->execute();
        }
        if($params['user_device_token'] != '') {
            $query = $db->prepare('select * from devices where user_id = :user_id');
            $query->bindParam(':user_id', $user['id']);
            if($query->execute()) {
                $device = $query->fetch(PDO::FETCH_NAMED);
                if($device) {
                    $query = $db->prepare('update devices set device_uid = :user_device_token,
                                                              type = :user_device_type,
                                                              modified = now(),
                                                              lastused = now()
                                                        where id = :device_id');
                    $query->bindParam(':user_device_token', $params['user_device_token']);
                    $query->bindParam(':user_device_type', $params['user_device_type']);
                    $query->bindParam(':device_id', $device['id']);
                } else {
                    $query = $db->prepare('insert into devices (device_uid,
                                                                type,
                                                                user_id,
                                                                modified,
                                                                lastused)
                                                        values (:user_device_token,
                                                                :user_device_type,
                                                                :user_id,
                                                                now(),
                                                                now())');
                    $query->bindParam(':user_device_token', $params['user_device_token']);
                    $query->bindParam(':user_device_type', $params['user_device_type']);
                    $query->bindParam(':user_id', $user['id']);
                }

                if($query->execute()) {
                    $me = getUserInformation($user);
                    $me['access_token'] = createUserAccessToken($user['id'], $user['email']);

                    $newRes = makeResultResponseWithObject($res, 200, $me);
                } else {
                    $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
                }
            } else {
                $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
            }
        } else {
            $me = getUserInformation($user);
            $me['access_token'] = createUserAccessToken($user['id'], $user['email']);

            $newRes = makeResultResponseWithObject($res, 200, $me);
        }
    } else {
        $newRes = makeResultResponseWithString($res, 400, 'Your email or password is invalid');
    }

    return $newRes;
}

function socialLogin($req, $res) {
    global $db;

    $params = $req->getParams();
    $query = $db->prepare('insert into users (email,
                                              name,
                                              password,
                                              profile_image,
                                              active,
                                              deactivate,
                                              modified) 
                                      values (:user_email,
                                              :user_name,
                                              HEX(AES_ENCRYPT(:user_pass, \'' . DB_USER_PASSWORD . '\')),
                                              :user_avatar_url,
                                              1,
                                              0,
                                              now())');

    $query->bindParam(':user_email', $params['user_email']);
    $query->bindParam(':user_name', $params['user_name']);
    $query->bindParam(':user_pass', $params['user_pass']);
    $query->bindParam(':user_avatar_url', $params['user_avatar_url']);

    if($query->execute()) {
        $query = $db->prepare('select * from users where id = :user_id');
        $query->bindParam(':user_id', $db->lastInsertId());
        if($query->execute()) {
            $user = $query->fetch(PDO::FETCH_NAMED);

            // register user settings
            $query = $db->prepare('insert into user_settings (user_id)
                                                      values (:user_id)');
            $query->bindParam(':user_id', $user['id']);
            if($query->execute()) {
                // register user device
                if($params['user_device_token'] != '') {

                    $query = $db->prepare('insert into devices (device_uid,
                                                                type,
                                                                user_id,
                                                                modified,
                                                                lastused)
                                                        values (:user_device_token,
                                                                :user_device_type,
                                                                :user_id,
                                                                now(),
                                                                now())');
                    $query->bindParam(':user_device_token', $params['user_device_token']);
                    $query->bindParam(':user_device_type', $params['user_device_type']);
                    $query->bindParam(':user_id', $user['id']);

                    if($query->execute()) {
                        $me = getUserInformation($user);
                        $me['access_token'] = createUserAccessToken($user['id'], $user['email']);

                        $newRes = makeResultResponseWithObject($res, 200, $me);
                    } else {
                        $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
                    }
                } else {
                    $me = getUserInformation($user);
                    $me['access_token'] = createUserAccessToken($user['id'], $user['email']);

                    $newRes = makeResultResponseWithObject($res, 200, $me);
                }
            } else {
                $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
            }

        } else {
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    } else {
        if($query->errorInfo()[1] == 1062) {
            $newRes = auth($req, $res);
        } else {
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    }

    return $newRes;
}

function getUserInformation($user) {
    $me = [];

    $me['User'] = $user;

    if($user['city_id'] > 0) {
        $city = json_decode(\Httpful\Request::get(WEB_SERVER . 'cities/' . $user['city_id'])->send(), true);
        if ($city) {
            $state = json_decode(\Httpful\Request::get(WEB_SERVER . 'states/' . $city['state_id'])->send(), true);
            if ($state) {
                $country = json_decode(\Httpful\Request::get(WEB_SERVER . 'countries/' . $state['country_id'])->send(), true);

                if ($country) {
                    $state['Country'] = $country;
                }

                $city['State'] = $state;
            }

            $me['City'] = $city;
        }
    }

    return $me;
}

function createUserAccessToken($user_id, $user_email) {
    global $db;

    $query = $db->prepare('delete from tokens where token_user_id = :user_id');
    $query->bindParam(':user_id', $user_id);
    $query->execute();

    $token_key = base64_encode('ProFceeAccessToken=>Start:'.$user_email.'at'.time().':End');
    $query = $db->prepare('insert into tokens (token_user_id,
                                               token_key,
                                               token_expire_at) 
                                        values (:token_user_id,
                                               HEX(AES_ENCRYPT(:token_key, \'' . DB_USER_PASSWORD . '\')),
                                               adddate(now(), INTERVAL 1 MONTH))');
    $query->bindParam(':token_user_id', $user_id);
    $query->bindParam(':token_key', $token_key);

    if($query->execute()) {
        $user_access_token = $token_key;
    } else {
        $user_access_token = $query->errorInfo()[2];
    }

    return $user_access_token;
}

function verifyEmail($req, $res) {
    global $db;

    $params = $req->getParams();

    $query = $db->prepare('update users set active = 1 where activation_code = :activation_code');
    $query->bindParam(':activation_code', $params['code']);
    if($query->execute()) {
        $newRes = $res->withStatus(302)->withHeader('Location', 'http://www.profcee.com/email_ver.html');
    } else {
        $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
    }

    return $newRes;
}

function forgotPassword($req, $res) {
    global $db;

    $params = $req->getParams();

    $query = $db->prepare('select * from users where email = :user_email');
    $query->bindParam(':user_email', $params['user_email']);
    if($query->execute()) {
        $user = $query->fetch(PDO::FETCH_NAMED);
        if($user) {
            $password_token = generateRandomString(6, 1);
            $message = 'Here is code that you need to enter in the app : ' . $password_token;
            sendEmail('Reset Your ProFcee App Password', $message, $user['email']);

            $query = $db->prepare('update users set password_token = :password_token
                                                where id = :user_id');
            $query->bindParam(':password_token', $password_token);
            $query->bindParam(':user_id', $user['id']);
            if($query->execute()) {
                $newRes = makeResultResponseWithString($res, 200, 'Sent reset code to your email address');
            } else {
                $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
            }
        } else {
            $newRes = makeResultResponseWithString($res, 400, 'Invalid email address');
        }
    } else {
        $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
    }

    return $newRes;
}

function resetPassword($req, $res) {
    global $db;

    $params = $req->getParams();

    $query = $db->prepare('select * from users where password_token = :password_token');
    $query->bindParam(':password_token', $params['code']);
    if($query->execute()) {
        $user = $query->fetch(PDO::FETCH_NAMED);
        if($user) {
            $query = $db->prepare('update users set password = HEX(AES_ENCRYPT(:user_pass, \'' . DB_USER_PASSWORD . '\'))
                                              where id = :user_id');
            $query->bindParam(':user_pass', $params['user_pass']);
            $query->bindParam(':user_id', $user['id']);
            if($query->execute()) {
                $newRes = makeResultResponseWithString($res, 200, 'Reset your password successfully');
            } else {
                $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
            }
        } else {
            $newRes = makeResultResponseWithString($res, 400, 'Invalid code');
        }
    } else {
        $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
    }

    return $newRes;
}

function updatePassword($req, $res) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $params = $req->getParams();

        $query = $db->prepare('select AES_DECRYPT(UNHEX(password), \'' . DB_USER_PASSWORD . '\') as user_pass from users where id = :user_id');
        $query->bindParam(':user_id', $user_id);
        if($query->execute()) {
            $result = $query->fetch(PDO::FETCH_NAMED);
            if ($result['user_pass'] == $params['current_pass']) {
                $query = $db->prepare('update users set password = HEX(AES_ENCRYPT(:user_pass, \'' . DB_USER_PASSWORD . '\'))
                                                  where id = :user_id');
                $query->bindParam(':user_id', $user_id);
                $query->bindParam(':user_pass', $params['new_pass']);

                if ($query->execute()) {
                    $newRes = makeResultResponseWithString($res, 200, 'Your password was updated successfully');
                } else {
                    $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
                }

            } else {
                $newRes = makeResultResponseWithString($res, 400, 'Your current password is wrong.');
            }
        } else {
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    } else {
        $newRes = makeResultResponseWithString($res, 401, 'Your token has expired. Please login again.');
    }

    return $newRes;
}

function updateUser($req, $res) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $params = $req->getParams();
        $files = $req->getUploadedFiles();

        $query = $db->prepare('select * from users where id = :user_id');
        $query->bindParam(':user_id', $user_id);
        if($query->execute()) {
            $old_user = $query->fetch(PDO::FETCH_NAMED);

            if (isset($files['avatar'])) {
                if (!empty($params['profile_image'])) {
                    unlink('assets/avatar/' . $params['profile_image']);
                }
                $user_avatar_url = 'Avatar_' . generateRandomString(40) . '.jpg';
                $files['avatar']->moveTo('assets/avatar/' . $user_avatar_url);
            } else {
                if(empty($params['profile_image'])
                    && !empty($old_user['profile_image'])) {
                    unlink('assets/avatar/' . $old_user['profile_image']);
                }
                $user_avatar_url = $params['profile_image'];
            }

            if (isset($files['banner'])) {
                if (!empty($params['banner_image'])) {
                    unlink('assets/banner/' . $params['banner_image']);
                }
                $user_banner_url = 'Banner_' . generateRandomString(40) . '.jpg';
                $files['banner']->moveTo('assets/banner/' . $user_banner_url);
            } else {
                if(empty($params['banner_image'])
                    && !empty($old_user['banner_image'])) {
                    unlink('assets/banner/' . $old_user['banner_image']);
                }
                $user_banner_url = $params['banner_image'];
            }

            $query = $db->prepare('update users set name = :user_name,
                                                    organisation = :user_organisation,
                                                    designation = :user_designation,
                                                    city_id = :user_city_id,
                                                    city = :user_city,
                                                    gender = :user_gender,
                                                    dob = :user_dob,
                                                    profile_image = :user_avatar_url,
                                                    banner_image = :user_banner_url
                                              where id = :user_id');

            $query->bindParam(':user_name', $params['name']);
            $query->bindParam(':user_organisation', $params['organisation']);
            $query->bindParam(':user_designation', $params['designation']);
            $query->bindParam(':user_city_id', $params['city_id']);
            $query->bindParam(':user_city', $params['city']);
            $query->bindParam(':user_gender', $params['gender']);
            $query->bindParam(':user_dob', $params['dob']);
            $query->bindParam(':user_avatar_url', $user_avatar_url);
            $query->bindParam(':user_banner_url', $user_banner_url);
            $query->bindParam(':user_id', $user_id);
            if ($query->execute()) {
                $query = $db->prepare('select * from users where id = :user_id');
                $query->bindParam(':user_id', $user_id);
                if($query->execute()) {
                    $user = $query->fetch(PDO::FETCH_NAMED);

                    //update trend location
                    $trend_location = 'Unknown';

                    $city = json_decode(\Httpful\Request::get(WEB_SERVER . 'cities/' . $user['city_id'])->send(), true);
                    if ($city) {
                        $state = json_decode(\Httpful\Request::get(WEB_SERVER . 'states/' . $city['state_id'])->send(), true);
                        if ($state) {
                            $country = json_decode(\Httpful\Request::get(WEB_SERVER . 'countries/' . $state['country_id'])->send(), true);
                            if($country) {
                                $trend_location = $city['name'] . ', ' . $country['name'];
                            }
                        }
                    }

                    $query = $db->prepare('update trends set city_id = :city_id,
                                                         location = :location
                                                   where user_id = :user_id');
                    $query->bindParam(':city_id', $user['city_id']);
                    $query->bindParam(':location', $trend_location);
                    $query->bindParam(':user_id', $user_id);

                    if($query->execute()) {
                        $newRes = makeResultResponseWithObject($res, 200, $user);
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

function deactivateUser($req, $res) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $query = $db->prepare('update users set deactivate = 1 where id = :user_id');
        $query->bindParam(':user_id', $user_id);
        if($query->execute()) {
            $newRes = makeResultResponseWithString($res, 200, 'Deactivated your account');
        } else {
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    } else {
        $newRes = makeResultResponseWithString($res, 401, 'Your token has expired. Please login again.');
    }

    return $newRes;
}

function getUser($req, $res, $args = []) {
    global $db;

    $query = $db->prepare('select id, name, gender, dob, organisation, designation, city_id, city, profile_image, banner_image, active, suspend
                            from users where id = :user_id');
    $query->bindParam(':user_id', $args['id']);
    if($query->execute()) {
        $user = $query->fetch(PDO::FETCH_NAMED);
        if($user) {
            $newRes = makeResultResponseWithObject($res, 200, getUserInformation($user));
        } else {
            $newRes = makeResultResponseWithObject($res, 204, false);
        }
    } else {
        $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
    }

    return $newRes;
}

function getUserTrends($req, $res, $args = []) {
    global $db;
    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $query = $db->prepare('select * from trends where deleted = 0 and user_id = :user_id
                                order by created desc');
        $query->bindParam(':user_id', $args['id']);
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

function getUserAgreedTrends($req, $res, $args = []) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $query = $db->prepare('select * from trends where deleted = 0 and id in (select trend_id from agrees where user_id = :user_id)
                                order by created desc');
        $query->bindParam(':user_id', $args['id']);
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

function logOut($req, $res, $args = []) {
    global $db;

    // remove token
    $query = $db->prepare('delete from tokens where token_user_id = :user_id');
    $query->bindParam(':user_id', $args['id']);
    if($query->execute()) {
        // remove device
        $query = $db->prepare('delete from devices where user_id = :user_id');
        $query->bindParam(':user_id', $args['id']);
        if($query->execute()) {
            $newRes = makeResultResponseWithString($res, 200, 'LogOut success!');
        } else {
            $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
        }
    } else {
        $newRes = makeResultResponseWithString($res, 400, $query->errorInfo()[2]);
    }

    return $newRes;
}

function searchUser($req, $res) {
    global $db;

    $user_id = validateUserAuthentication($req);
    if($user_id) {
        $params = $req->getParams();
        if($params['type'] == SEARCH_USER_BY_NAME) {
            $sql = "select * from users where suspend = 0 and deactivate = 0 and LOWER(name) LIKE '%" . $params['keyword'] . "%'";
            $query = $db->prepare($sql);
        } else {
            $query = $db->prepare('select * from users where suspend = 0 and deactivate = 0 and LOWER(email) = :user_email');
            $query->bindParam(':user_email', $params['keyword']);
        }

        if($query->execute()) {
            $users = $query->fetchAll(PDO::FETCH_ASSOC);

            $user_infos = [];
            foreach($users as $user) {
                array_push($user_infos, getUserInformation($user));
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