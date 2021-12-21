<?php

rex_yform_manager_dataset::setModelClass('rex_simple_oauth_client', rex_simple_oauth_client::class);
rex_yform_manager_dataset::setModelClass('rex_simple_oauth_token', rex_simple_oauth_token::class);
rex_yform_manager_dataset::setModelClass('rex_simple_oauth_authcode', rex_simple_oauth_authcode::class);

rex_extension::register(
    ['YFORM_DATA_UPDATED', 'YFORM_DATA_ADDED'],
    static function ($ep) {
        $params = $ep->getParams();
        $table = $params['table'];

        if ('rex_simple_oauth_client' == $table->getTableName()) {
            $data_id = $params['data_id'];
            $data = $params['data'];
            $secret = $data->getValue('secret');

            if (!isset($params['old_data']['secret']) || $params['old_data']['secret'] != $secret) {
                $secret = password_hash($secret, PASSWORD_BCRYPT);
                rex_sql::factory()->setQuery('update rex_simple_oauth_client set secret = :secret where id = :id', ['secret' => $secret, 'id' => $data_id]);
            }
        }
    }
);

if (rex::isFrontend()) {
    rex_extension::register(
        'PACKAGES_INCLUDED',
        static function ($params) {
            if (false !== $response = \REDAXO\Simple_OAuth\Simple_OAuth::init()) {
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
        },
        rex_extension::LATE
    );
}
