<?php

/** @var rex_addon $this */

// Die Subpages müssen dem Titel nicht mehr übergeben werden
echo rex_view::title(rex_i18n::msg('simple_oauth_title'));

// Subpages können über diese Methode eingebunden werden. So ist sichergestellt, dass auch Subpages funktionieren,
// die von anderen Addons/Plugins hinzugefügt wurden
rex_be_controller::includeCurrentPageSubPath();
