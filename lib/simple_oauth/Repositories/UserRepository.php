<?php

namespace REDAXO\Simple_OAuth\Repositories;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use REDAXO\Simple_OAuth\Entities\UserEntity;

class UserRepository implements UserRepositoryInterface
{
    public function getCurrentUserEntity()
    {
        try {
            $user = new UserEntity();
            $ycom_user_login_field = \rex_config::get('ycom/auth', 'login_field');
            $user->setIdentifier(\rex_ycom_user::getMe()->getValue($ycom_user_login_field));
            return $user;
        } catch (\Exception $exception) {
            return null;
        }
    }

    public function getUserEntityByUserCredentials($userIdentifier, $password, $grantType, ClientEntityInterface $clientEntity)
    {
        /*
          login_status
          0: not logged in
          1: logged in
          2: has logged in
          3: has logged out
          4: login failed
        */

        $params = [];
        $params['loginName'] = $userIdentifier;
        $params['loginPassword'] = $password;
        $params['loginStay'] = false;

        $status = \rex_ycom_auth::login($params);

        if (2 != $status) {
            return false;
        }

        $ycom_user_login_field = \rex_config::get('ycom/auth', 'login_field');

        $UserObject = \rex_ycom_user::query()
            ->where($ycom_user_login_field, $userIdentifier)
            ->findOne();

        if (!$UserObject) {
            return false;
        }

        $user = new UserEntity();
        $user->setIdentifier($UserObject->getValue($ycom_user_login_field));
        return $user;
    }
}
