<?php

$table_name = 'rex_simple_oauth_authcode';
$_REQUEST['table_name'] = $table_name;

\rex_extension::register(
    'YFORM_MANAGER_DATA_PAGE_HEADER',
    function( \rex_extension_point $ep ) {
        if ($ep->getParam('yform')->table->getTableName() === $ep->getParam('table_name')) {
            return '';
        }
    },
    \rex_extension::EARLY,['table_name'=>$table_name]
);

include \rex_path::plugin('yform','manager','pages/data_edit.php');
