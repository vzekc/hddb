<?php
// Router for the PHP development server.  Serves webapp/html and fakes
// what Apache provides in production: the OIDC identity for /auth/ paths.
//
//   webapp/dev/run.sh   sets everything up and starts the server

$_SERVER['OIDC_CLAIM_sub'] = getenv('DEV_USER_ID') ?: '4711';
$_SERVER['OIDC_CLAIM_nickname'] = getenv('DEV_USER_NAME') ?: 'devuser';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . '/../html' . $path;
if ($path !== '/' && is_file($file)) {
    if (str_ends_with($file, '.php')) {
        chdir(dirname($file));
        require $file;
        return true;
    }
    return false;  // static file, let the dev server serve it
}
chdir(__DIR__ . '/../html');
require __DIR__ . '/../html/index.html';
return true;
