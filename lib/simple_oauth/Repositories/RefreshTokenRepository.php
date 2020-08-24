<?php

namespace REDAXO\Simple_OAuth\Repositories;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use REDAXO\Simple_OAuth\Entities\RefreshTokenEntity;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity)
    {
        $accessTokenEntity = $refreshTokenEntity->getAccessToken();
        $identifier = $refreshTokenEntity->getIdentifier();

        $client_id = $accessTokenEntity->getClient()->getIdentifier();
        $scopes = $this->formatScopesForStorage($accessTokenEntity->getScopes());
        $expires_at = date('Y-m-d H:i:s', $refreshTokenEntity->getExpiryDateTime()->getTimestamp());

        $user_identifier = $accessTokenEntity->getUserIdentifier();
        $ycom_user_login_field = \rex_config::get('ycom/auth', 'login_field');

        $UserObject = \rex_ycom_user::query()->where($ycom_user_login_field, $user_identifier)->findOne();

        if ($UserObject) {
            \rex_simple_oauth_token::create()
                ->setValue('token_type', 'refresh')
                ->setValue('token', $identifier)
                ->setValue('user_id', $UserObject->getId())
                ->setValue('client_id', $client_id)
                ->setValue('scopes', $scopes)
                ->setValue('revoked', 0)
                ->setValue('expires_at', $expires_at)
                ->save();
        }
    }

    public function revokeRefreshToken($tokenId)
    {
        $token = \rex_simple_oauth_token::query()->where('token', $tokenId)->where('token_type', 'refresh')->findOne();
        if ($token) {
            $token
                ->setValue('revoked', 1)
                ->save();
        }
    }

    public function isRefreshTokenRevoked($tokenId)
    {
        $token = \rex_simple_oauth_token::query()->where('token', $tokenId)->where('token_type', 'refresh')->findOne();
        if ($token) {
            return 1 == $token->getValue('revoked') ? true : false;
        }

        return false;
    }

    public function getNewRefreshToken()
    {
        return new RefreshTokenEntity();
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
