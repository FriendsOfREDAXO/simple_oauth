<?php

namespace REDAXO\Simple_OAuth;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use REDAXO\Simple_OAuth\Repositories\AccessTokenRepository;
use REDAXO\Simple_OAuth\Repositories\AuthCodeRepository;
use REDAXO\Simple_OAuth\Repositories\ClientRepository;
use REDAXO\Simple_OAuth\Repositories\RefreshTokenRepository;
use REDAXO\Simple_OAuth\Repositories\ScopeRepository;
use REDAXO\Simple_OAuth\Repositories\UserRepository;

class Simple_OAuth
{
    public static $basePath = '/oauth2/';
    private static $YComUserFields = [
        'id', 'firstname', 'name', 'email', 'ycom_groups',
    ];
    public static $expirationTimeAuthCode = 60;
    public static $expirationTimeAccessCode = 3600 * 24 * 30;
    public static $expirationTimeRefreshCode = 3600 * 24 * 30 * 6;

    public static function init()
    {
        $initObject = new self();
        if (substr($initObject->getCurrentPath(), 0, \strlen(self::$basePath)) != self::$basePath) {
            return false;
        }

        ini_set('arg_separator.output', '&');

        $clientRepository = new ClientRepository();
        $scopeRepository = new ScopeRepository();
        $accessTokenRepository = new AccessTokenRepository();
        $authCodeRepository = new AuthCodeRepository();
        $refreshTokenRepository = new RefreshTokenRepository();
        $userRepository = new UserRepository();

        $ExpirationTimeAuthCode = \rex_addon::get('simple_oauth')->getConfig('expiration_time_auth_code', self::$expirationTimeAuthCode);
        $ExpirationTimeAccessCode = \rex_addon::get('simple_oauth')->getConfig('expiration_time_access_code', self::$expirationTimeAccessCode);
        $ExpirationTimeRefreshCode = \rex_addon::get('simple_oauth')->getConfig('expiration_time_refresh_code', self::$expirationTimeRefreshCode);

        $server = new AuthorizationServer(
            $clientRepository,
            $accessTokenRepository,
            $scopeRepository,
            self::getPrivateKey(),
            $initObject->getEncryptionKey()
        );

        $authCodeGrant = new AuthCodeGrant(
            $authCodeRepository,
            $refreshTokenRepository,
            new \DateInterval('PT'.$ExpirationTimeAuthCode.'S') // PT10M = authorization codes will expire after 10 minutes
        );
        $authCodeGrant->setRefreshTokenTTL(new \DateInterval('PT'.$ExpirationTimeRefreshCode.'S')); // P1M - refresh tokens will expire after 1 month
        $server->enableGrantType($authCodeGrant, new \DateInterval('PT'.$ExpirationTimeAccessCode.'S')); // PT1H = access tokens will expire after 1 hour

        $refreshTokenGrant = new RefreshTokenGrant(
            $refreshTokenRepository
        );

        $server->enableGrantType($refreshTokenGrant, new \DateInterval('PT'.$ExpirationTimeAccessCode.'S')); // PT1H = access tokens will expire after 1 hour

        /* legacy */
        // https://oauth2.thephpleague.com/authorization-server/resource-owner-password-credentials-grant/
        $passwortGrant = new PasswordGrant(
            $userRepository,
            $refreshTokenRepository
        );
        $passwortGrant->setRefreshTokenTTL(new \DateInterval('PT'.$ExpirationTimeRefreshCode.'S')); // P1M - refresh tokens will expire after 1 month
        $server->enableGrantType($passwortGrant, new \DateInterval('PT'.$ExpirationTimeAccessCode.'S')); // PT1H = access tokens will expire after 1 hour

        /*
        $clientCredentialGrant = new ClientCredentialsGrant();
        // access_token: https://oauth2.thephpleague.com/authorization-server/client-credentials-grant/
        */

        // request und response PSR7
        $request = ServerRequest::fromGlobals();
        $response = new Response();

        $currentPathAsArray = explode('/', $request->getUri()->getPath());
        switch ($currentPathAsArray[2]) {
            case 'authorize':
                try {
                    // get
                    // ist hier dafür da, um Informationen für das Login zu verwenden. z.B. Logo oder ähnliches
                    if (@$_SESSION['Simple_OAuth_AuthRequest']) {
                        $authRequest = $_SESSION['Simple_OAuth_AuthRequest']; // unserialize(\rex_ycom_auth::getSessionVar('Simple_OAuth_AuthRequest'));
                    } else {
                        $authRequest = $server->validateAuthorizationRequest($request);
                    }

                    $_SESSION['Simple_OAuth_AuthRequest'] = $authRequest;

                    if (null == $ycomUser = \rex_ycom_user::getMe()) {
                        $loginUrl = rex_getUrl(\rex_config::get('ycom/auth', 'article_id_login'), '', [
                            'returnTo' => self::$basePath.'authorize',
                        ]);
                        \rex_response::sendRedirect($loginUrl);
                        die();
                    }

                    unset($_SESSION['Simple_OAuth_AuthRequest']);

                    $userRepository = new UserRepository();
                    $user = $userRepository->getCurrentUserEntity();
                    $authRequest->setUser($user);
                    $authRequest->setAuthorizationApproved(true);

                    return $server->completeAuthorizationRequest($authRequest, $response);
                } catch (OAuthServerException $exception) {
                    \rex_ycom_auth::unsetSessionVar('Simple_OAuth_AuthRequest');
                    return $exception->generateHttpResponse($response);
                } catch (\rex_sql_exception $exception) {
                    $response->getBody()->write('Error in OAuth. Code: rsqle');
                    return $response->withStatus(500);
                } catch (\Exception $exception) {
                    $response->getBody()->write('Error in OAuth. Code: geces '.$exception->getMessage());
                    return $response->withStatus(500);
                }
                break;

            case 'token':
                // Post
                try {
                    return $server->respondToAccessTokenRequest($request, $response);
                } catch (OAuthServerException $exception) {
                    return $exception->generateHttpResponse($response);
                } catch (\Exception $exception) {
                    $response->getBody()->write($exception->getMessage());
                    return $response->withStatus(500);
                }
                break;

            case 'profile':
                // any method
                // own spezific call ..
                try {
                    $authheader = $request->getHeader('Authorization')[0];
                    $token = trim(str_replace('BEARER ', '', $authheader));
                    if (!$token) {
                        // extract token from request body as a fallback
                        $pb = $request->getParsedBody();
                        $token = isset($pb['token']) ?? null;
                    }

                    if (!$token) {
                        // return error
                        $response->getBody()->write('{"error":"Token is required"}');
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
                    }

                    // decode the token
                    [$header, $payload, $signature] = explode('.', $token);
                    $data = base64_decode($payload);
                    $data = json_decode($data);
                    $token_id = $data->jti;
                    $user_identifier = $data->sub;
                    $ycom_user_login_field = \rex_config::get('ycom/auth', 'login_field');

                    $tokenObject = \rex_simple_oauth_token::query()
                        ->where('revoked', 0)
                        ->where('token', $token_id)
                        ->where('expires_at', date('Y-m-d H:i:s'), '>')
                        ->findOne();

                    if (!$tokenObject) {
                        // return error
                        $response->getBody()->write('{"error":"Token is invalid"}');
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
                    }

                    $UserObject = \rex_ycom_user::query()->where($ycom_user_login_field, $user_identifier)->findOne();

                    if (
                        $UserObject &&
                        $tokenObject->getValue('user_id') == $UserObject->getId()
                    ) {
                        if ($UserObject->getValue('status') > 0) {
                            $User = [];
                            foreach (self::$YComUserFields as $Field) {
                                if ('ycom_groups' == $Field) {
                                    $User[$Field] = ('' == $UserObject->getValue($Field)) ? [] : explode(',', $UserObject->getValue($Field));
                                } else {
                                    $User[$Field] = $UserObject->getValue($Field);
                                }
                            }

                            $payload = json_encode($User);
                            $response->getBody()->write($payload);
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                        }
                    }

                    $response->getBody()->write('{"error":"User not found with identifier: ' . $user_identifier . '"}');
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
                } catch (\Exception $exception) {
                    $response->getBody()->write($exception->getMessage());
                    return $response->withStatus(500);
                }
                break;
            case 'access_token':
                try {
                    return $server->respondToAccessTokenRequest($request, $response);
                } catch (OAuthServerException $exception) {
                    return $exception->generateHttpResponse($response);
                } catch (\Exception $exception) {
                    $response->getBody()->write($exception->getMessage());
                    return $response->withStatus(500);
                }
                break;
            default:
                $response->getBody()->write('Wrong OAuth Function Call');
                return $response->withStatus(500);
        }
    }

    public static function getPublicKey()
    {
        $file = \rex_addon::get('simple_oauth')->getDataPath('public.key');
        if (file_exists($file)) {
            return \rex_file::get($file);
        }
        return '';
    }

    public static function getPrivateKey()
    {
        $file = \rex_addon::get('simple_oauth')->getDataPath('private.key');
        if (file_exists($file)) {
            return \rex_file::get($file);
        }
        return '';
    }

    private function getCurrentPath()
    {
        $url = parse_url($_SERVER['REQUEST_URI']);
        return $url['path'] ?? '/';
    }

    public static function getEncryptionKey()
    {
        return \rex_config::get('simple_oauth', 'encryption_key');
    }
}
