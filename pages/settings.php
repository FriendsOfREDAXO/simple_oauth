<?php

/**
 * simple_oauth.
 *
 * @package redaxo\simple_oauth
 *
 * @var rex_plugin $this
 */


use REDAXO\Simple_OAuth\Simple_OAuth;

$func = rex_request('func', 'string');

if ('update' == $func) {

    $this->setConfig('expiration_time_auth_code', rex_post('simple_oauth')['expiration_time_auth_code']);
    $this->setConfig('expiration_time_access_code', rex_post('simple_oauth')['expiration_time_access_code']);
    $this->setConfig('expiration_time_refresh_code', rex_post('simple_oauth')['expiration_time_refresh_code']);
    $this->setConfig('authorize_login_article_id', rex_post('simple_oauth')['authorize_login_article_id']);

    echo rex_view::success($this->i18n('settings_saved'));
}

$content = '';

$formElements = [];
$n = [];

$selAuthCode = new rex_select();
$selAuthCode->setStyle('class="form-control"');
$selAuthCode->setAttribute('class', 'form-control selectpicker');
$selAuthCode->setSize(1);
$selAuthCode->addOption('10 min', 60 * 10);
$selAuthCode->addOption('30 min', 60 * 30);
$selAuthCode->addOption('1 hour', 3600);
$selAuthCode->addOption('6 hours', 3600 * 6);
$selAuthCode->addOption('12 hours', 3600 * 12);
$selAuthCode->addOption('1 day', 3600 * 24);
$selAuthCode->addOption('1 week', 3600 * 24 * 7);
$selAuthCode->addOption('1 month', 3600 * 24 * 30);
$selAuthCode->addOption('6 months', 3600 * 24 * 30 * 6);
$selAuthCode->addOption('1 year', 3600 * 24 * 30 * 12);

$selAccess = clone $selAuthCode;
$selRefresh = clone $selAuthCode;

$selAuthCode->setName('simple_oauth[expiration_time_auth_code]');
$selAuthCode->setSelected($this->getConfig('expiration_time_auth_code', Simple_OAuth::$expirationTimeAuthCode));

$selAccess->setName('simple_oauth[expiration_time_access_code]');
$selAccess->setSelected($this->getConfig('expiration_time_access_code', Simple_OAuth::$expirationTimeAccessCode));

$selRefresh->setName('simple_oauth[expiration_time_refresh_code]');
$selRefresh->setSelected($this->getConfig('expiration_time_refresh_code', Simple_OAuth::$expirationTimeRefreshCode));

$n = [];
$n['label'] = '<label for="rex-id-lang">' . rex_i18n::msg('simple_oauth_expiration_auth_code_time') . '</label>';
$n['field'] = $selAuthCode->get();
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-id-lang">' . rex_i18n::msg('simple_oauth_expiration_access_code_time') . '</label>';
$n['field'] = $selAccess->get();
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-id-lang">' . rex_i18n::msg('simple_oauth_expiration_refresh_code_time') . '</label>';
$n['field'] = $selRefresh->get();
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-id-lang">' . rex_i18n::msg('simple_oauth_authorize_login_article_id') . '</label>';
$n['field'] = '<input class="form-control" type="text" name="simple_oauth[authorize_login_article_id]" value="'.$this->getConfig('authorize_login_article_id', 0).'" />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-id-lang">' . rex_i18n::msg('simple_oauth_keys') . '</label>';
$n['field'] = [];
$privateKey = Simple_OAuth::getPrivateKey();
if ('' == $privateKey) {
    $n['field'][] = rex_view::error(rex_i18n::msg('simple_oauth_privatekey_warning'));
} else {
    $n['field'][] = rex_view::success(rex_i18n::msg('simple_oauth_privatekey_exist'));
}
$publicKey = Simple_OAuth::getPublicKey();
if ('' == $publicKey) {
    $n['field'][] = rex_view::error(rex_i18n::msg('simple_oauth_publickey_warning'));
} else {
    $n['field'][] = rex_view::success(rex_i18n::msg('simple_oauth_publickey_exist'));
}
$n['field'] = implode('', $n['field']);
$n['note'] = rex_i18n::msg('simple_oauth_data_folder', '', rex_addon::get('simple_oauth')->getDataPath(), ' https://oauth2.thephpleague.com/installation/');
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-id-lang">' . rex_i18n::msg('simple_oauth_authorize_url') . '</label>';
$n['field'] = rex::getServer().'oauth2/authorize';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-id-lang">' . rex_i18n::msg('simple_oauth_token_url') . '</label>';
$n['field'] = rex::getServer().'oauth2/token';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-id-lang">' . rex_i18n::msg('simple_oauth_profile_url') . '</label>';
$n['field'] = rex::getServer().'oauth2/profile';
$formElements[] = $n;

$fragment = new \rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$formElements = [];
$n = [];
$n['field'] = '<a class="btn btn-abort" href="'.\rex_url::currentBackendPage().'">'.\rex_i18n::msg('form_abort').'</a>';
$formElements[] = $n;

$n = [];
$n['field'] = '<button class="btn btn-apply rex-form-aligned" type="submit" name="send" value="1"'.\rex::getAccesskey(
    \rex_i18n::msg('update'),
    'apply'
).'>'.\rex_i18n::msg('update').'</button>';
$formElements[] = $n;

$fragment = new \rex_fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$fragment = new \rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', $this->i18n('settings'), false);
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$section = $fragment->parse('core/page/section.php');

echo '
    <form action="'.\rex_url::currentBackendPage().'" method="post">
        <input type="hidden" name="func" value="update" />
        '.$section.'
    </form>
';
