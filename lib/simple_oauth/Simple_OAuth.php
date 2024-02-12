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
    public const FORCE_NOTHING = 0;
    public const FORCE_AUTHORIZE = 1;
    public static $basePath = '/oauth2/';
    private static $scopes = [
        'basic' => [
            'fields' => [
                'id',
                'login',
                'email',
            ],
            'description' => '',
        ],
        'identity' => [
            'fields' => [
                'id',
                'login',
                'email',
                'firstname',
                'name',
            ],
            'description' => '',
        ],
        'groups' => [
            'fields' => [
                'ycom_groups',
            ],
            'description' => '',
        ],
    ];

    public static $expirationTimeAuthCode = 600;
    public static $expirationTimeAccessCode = 3600 * 24 * 30;
    public static $expirationTimeRefreshCode = 3600 * 24 * 30 * 6;

    /**
     * @throws \Exception
     * @return false|Response|\Psr\Http\Message\ResponseInterface
     */
    public static function init($forceGrant = self::FORCE_NOTHING)
    {
        $initObject = new self();
        if (self::FORCE_NOTHING == $forceGrant && substr($initObject->getCurrentPath(), 0, \strlen(self::$basePath)) != self::$basePath) {
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

        // authorize grant
        $authCodeGrant = new AuthCodeGrant(
            $authCodeRepository,
            $refreshTokenRepository,
            new \DateInterval('PT'.$ExpirationTimeAuthCode.'S') // PT10M = authorization codes will expire after 10 minutes
        );
        $authCodeGrant->setRefreshTokenTTL(new \DateInterval('PT'.$ExpirationTimeRefreshCode.'S')); // P1M - refresh tokens will expire after 1 month
        $server->enableGrantType($authCodeGrant, new \DateInterval('PT'.$ExpirationTimeAccessCode.'S')); // PT1H = access tokens will expire after 1 hour

        // refresh grant
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

        if (self::FORCE_AUTHORIZE == $forceGrant || 'authorize' == $currentPathAsArray[2]) {

            try {
                $authRequest = $server->validateAuthorizationRequest($request);
                $queryParams = $request->getQueryParams() ?? [];

                if (null === \rex_ycom_user::getMe()) {
                    $loginUrl = rex_getUrl(\rex_config::get('simple_oauth', 'authorize_login_article_id'), '', $queryParams, '&');
                    \rex_response::sendRedirect($loginUrl);
                }

                $userRepository = new UserRepository();
                $user = $userRepository->getCurrentUserEntity();
                $authRequest->setUser($user);
                $authRequest->setAuthorizationApproved(true);

                return $server->completeAuthorizationRequest($authRequest, $response);
            } catch (OAuthServerException $exception) {
                return $exception->generateHttpResponse($response);
            } catch (\rex_sql_exception $exception) {
                $response->getBody()->write('Error in OAuth. Code: rsqle');
                return $response->withStatus(500);
            } catch (\Exception $exception) {
                $response->getBody()->write('Error in OAuth. Code: geces '.$exception->getMessage());
                return $response->withStatus(500);
            }

        }

        switch ($currentPathAsArray[2]) {
            case 'authorize':
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
                    $authheader = $request->getHeader('Authorization')[0] ?? '';
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
                        $UserObject
                        && $tokenObject->getValue('user_id') == $UserObject->getId()
                    ) {
                        if ($UserObject->getValue('status') > 0) {

                            /** @var \rex_simple_oauth_client $client */
                            $client = $tokenObject->getRelatedDataset('client_id');
                            $availableScopes = self::getScopes();
                            $ClientScopes = ('' == $client->getValue('scopes')) ? [] : explode(',', $client->getValue('scopes'));

                            $User = [];
                            $User['id'] = $UserObject->getId();
                            foreach ($ClientScopes as $scope) {
                                if (\array_key_exists($scope, $availableScopes)) {
                                    foreach ($availableScopes[$scope]['fields'] as $field) {
                                        $User[$field] = $UserObject->getValue($field);
                                    }
                                }
                            }

                            $User = \rex_extension::registerPoint(new \rex_extension_point('SIMPLE_OAUTH_PROFILE_USER', $User, [
                                'token' => $tokenObject,
                                'user' => $UserObject,
                                'request' => $request,
                                'response' => $response,
                            ]));

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

    /**
     * @return string
     */
    public static function getPublicKey()
    {
        $file = \rex_addon::get('simple_oauth')->getDataPath('public.key');
        if (file_exists($file)) {
            return (string) \rex_file::get($file);
        }
        return '';
    }

    /**
     * @return string
     */
    public static function getPrivateKey()
    {
        $file = \rex_addon::get('simple_oauth')->getDataPath('private.key');
        if (file_exists($file)) {
            return (string) \rex_file::get($file);
        }
        return '';
    }

    /**
     * @return string
     */
    private function getCurrentPath()
    {
        $url = parse_url($_SERVER['REQUEST_URI']);
        return (string) $url['path'] ?? '/';
    }

    /**
     * @return array|mixed|mixed[]
     */
    public static function getEncryptionKey()
    {
        $encryptionKey = \rex_config::get('simple_oauth', 'encryption_key');
        if ('' == $encryptionKey) {
            \rex_config::set('simple_oauth', 'encryption_key', base64_encode(random_bytes(32)));
            $encryptionKey = \rex_config::get('simple_oauth', 'encryption_key');
        }
        return $encryptionKey;
    }

    public static function addScope(string $name, array $fields, string $description = '')
    {
        self::$scopes[$name] = ['fields' => $fields, 'description' => $description];
    }

    /**
     * @return array[]
     */
    public static function getScopes()
    {
        return self::$scopes;
    }

    public static function getYFormChoiceScopes()
    {
        $choices = [];
        foreach (self::getScopes() as $scope => $_) {
            $choices[$scope] = $scope. ' ['.implode(', ', $_['fields']).']';
        }
        return $choices;
    }

    public static function authorizeGrant()
    {
        $response = self::init(self::FORCE_AUTHORIZE);
        if (false !== $response) {
            self::sendResponse($response);
        }

    }

    public static function sendResponse($response)
    {
        /* var $response GuzzleHttp\Psr7\Response */

        // if (500 == $response->getStatusCode()) { dump($response); exit; }
        // if (302 == $response->getStatusCode()) { dump($response); exit; }

        $http_line = sprintf(
            'HTTP/%s %s %s',
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase()
        );
        header($http_line, true, $response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header("$name: $value", false);
            }
        }
        $stream = $response->getBody();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        while (!$stream->eof()) {
            echo $stream->read(1024 * 8);
        }
        exit;
    }
}
