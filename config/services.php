<?php
// M-Pesa
define('MPESA_API_HOST', 'api.sandbox.vm.co.mz');
define('MPESA_API_KEY', 'SUA_API_KEY_AQUI');
define('MPESA_PUBLIC_KEY', 'SUA_PUBLIC_KEY_AQUI');
define('MPESA_SERVICE_PROVIDER_CODE', 'SUA_SERVICE_PROVIDER_CODE_AQUI');
define('MPESA_INITIATOR_IDENTIFIER', 'SEU_INITIATOR_IDENTIFIER_AQUI');
define('MPESA_SECURITY_CREDENTIAL', 'SUA_SECURITY_CREDENTIAL_AQUI');
// e-Mola
define('EMOLA_API_URL', 'https://api.emola.co.mz/v1/payment');
define('EMOLA_API_KEY', 'SUA_API_KEY_AQUI');
define('EMOLA_MERCHANT_ID', 'SEU_MERCHANT_ID_AQUI');
// Google OAuth
define('GOOGLE_CLIENT_ID', 'SEU_GOOGLE_CLIENT_ID');
define('GOOGLE_CLIENT_SECRET', 'SEU_GOOGLE_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI', BASE_URL . 'auth_callback.php?provider=google');
// Facebook OAuth
define('FACEBOOK_APP_ID', 'SEU_FACEBOOK_APP_ID');
define('FACEBOOK_APP_SECRET', 'SEU_FACEBOOK_APP_SECRET');
define('FACEBOOK_REDIRECT_URI', BASE_URL . 'auth_callback.php?provider=facebook');