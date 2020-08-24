<?php

namespace REDAXO\Simple_OAuth\Repositories;

use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use REDAXO\Simple_OAuth\Entities\ClientEntity;

class ClientRepository implements ClientRepositoryInterface
{
    public function getClientEntity($clientIdentifier)
    {
        try {
            $clientObject = \rex_simple_oauth_client::get($clientIdentifier);
        } catch (\Exception $exception) {
            return false;
        }

        if (!$clientObject) {
            return false;
        }

        $client = new ClientEntity();

        $client->setIdentifier($clientObject->getId());
        $client->setName($clientObject->getValue('label'));
        $client->setRedirectUri($clientObject->getValue('redirect'));
        $client->setConfidential(1 == $clientObject->getValue('is_confidential') ? true : false);

        return $client;
    }

    public function validateClient($clientIdentifier, $clientSecret, $grantType)
    {
        try {
            $clientObject = \rex_simple_oauth_client::get($clientIdentifier);
        } catch (\Exception $exception) {
            return false;
        }

        if (!$clientObject) {
            return false;
        }

        if (false === password_verify($clientSecret, $clientObject->getValue('secret'))) {
            return false;
        }

        return true;
    }
}
