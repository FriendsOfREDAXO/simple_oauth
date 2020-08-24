<?php

rex_yform_manager_table::deleteCache();

rex_config::set('simple_oauth', 'encryption_key', base64_encode(random_bytes(32)));

/** @var rex_addon $this */

$content = rex_file::get(rex_path::addon('simple_oauth', 'install/tablesets/yform_oauth_tables.json'));
rex_yform_manager_table_api::importTablesets($content);

rex_delete_cache();
rex_yform_manager_table::deleteCache();

rex_dir::create(rex_addon::get('simple_oauth')->getDataPath());
