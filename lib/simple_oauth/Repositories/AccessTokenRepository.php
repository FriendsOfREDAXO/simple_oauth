<?php

namespace REDAXO\Simple_OAuth\Repositories;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use REDAXO\Simple_OAuth\Entities\AccessTokenEntity;

class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity)
    {
        $identifier = $accessTokenEntity->getIdentifier();
        $client_id = $accessTokenEntity->getClient()->getIdentifier();
        $scopes = $this->formatScopesForStorage($accessTokenEntity->getScopes());
        $expires_at = date('Y-m-d H:i:s', $accessTokenEntity->getExpiryDateTime()->getTimestamp());

        $user_identifier = $accessTokenEntity->getUserIdentifier();
        $ycom_user_login_field = \rex_config::get('ycom/auth', 'login_field');

        $UserObject = \rex_ycom_user::query()->where($ycom_user_login_field, $user_identifier)->findOne();

        if ($UserObject) {
            \rex_simple_oauth_token::create()
                ->setValue('token_type', 'access')
                ->setValue('token', $identifier)
                ->setValue('user_id', $UserObject->getId())
                ->setValue('client_id', $client_id)
                ->setValue('scopes', $scopes)
                ->setValue('revoked', 0)
                ->setValue('expires_at', $expires_at)
                ->save();
        }
    }

    public function revokeAccessToken($tokenId)
    {
        $token = \rex_simple_oauth_token::query()->where('token', $tokenId)->where('token_type', 'access')->findOne();
        if ($token) {
            $token
                ->setValue('revoked', 1)
                ->save();
        }
    }

    public function isAccessTokenRevoked($tokenId)
    {
        $token = \rex_simple_oauth_token::query()->where('token', $tokenId)->where('token_type', 'access')->findOne();
        if ($token) {
            return 1 == $token->getValue('revoked') ? true : false;
        }
        return true;
    }

    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, $userIdentifier = null)
    {
        $accessToken = new AccessTokenEntity();
        $accessToken->setClient($clientEntity);
        foreach ($scopes as $scope) {
            $accessToken->addScope($scope);
        }
        $accessToken->setUserIdentifier($userIdentifier);

        return $accessToken;
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
