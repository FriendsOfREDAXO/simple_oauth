<?php

namespace REDAXO\Simple_OAuth\Repositories;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use REDAXO\Simple_OAuth\Entities\AuthCodeEntity;

class AuthCodeRepository implements AuthCodeRepositoryInterface
{
    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity)
    {
        $identifier = $authCodeEntity->getIdentifier();
        $client_id = $authCodeEntity->getClient()->getIdentifier();
        $scopes = $this->formatScopesForStorage($authCodeEntity->getScopes());
        $expires_at = date('Y-m-d H:i:s', $authCodeEntity->getExpiryDateTime()->getTimestamp());

        $user_identifier = $authCodeEntity->getUserIdentifier();
        $ycom_user_login_field = \rex_config::get('ycom/auth', 'login_field');

        $UserObject = \rex_ycom_user::query()->where($ycom_user_login_field, $user_identifier)->findOne();

        if ($UserObject) {
            \rex_simple_oauth_authcode::create()
                ->setValue('code', $identifier)
                ->setValue('user_id', $UserObject->getId())
                ->setValue('client_id', $client_id)
                ->setValue('scopes', $scopes)
                ->setValue('revoked', 0)
                ->setValue('expires_at', $expires_at)
                ->save();
        }
    }

    public function revokeAuthCode($codeId)
    {
        $authcode = \rex_simple_oauth_authcode::query()->where('code', $codeId)->findOne();
        if ($authcode) {
            $authcode
                ->setValue('revoked', 1)
                ->save();
        } else {
            error_log("Auth code $codeId revoked failed.");
        }
    }

    public function isAuthCodeRevoked($codeId)
    {
        $authcode = \rex_simple_oauth_authcode::query()->where('code', $codeId)->findOne();
        if ($authcode) {
            return 1 == $authcode->getValue('revoked') ? true : false;
        }
        return true;
    }

    public function getNewAuthCode()
    {
        return new AuthCodeEntity();
    }

    public function formatScopesForStorage(array $scopes)
    {
        return json_encode($this->scopesToArray($scopes));
    }

    public function scopesToArray(array $scopes)
    {
        return array_map(static function ($scope) {
            return $scope->getIdentifier();
        }, $scopes);
    }
}
