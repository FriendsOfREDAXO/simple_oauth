<?php

/** @var rex_addon $this */

$content = rex_file::get(rex_path::addon('simple_oauth', 'install/tablesets/yform_oauth_tables.json'));
rex_yform_manager_table_api::importTablesets($content);

rex_dir::create(rex_addon::get('simple_oauth')->getDataPath());
