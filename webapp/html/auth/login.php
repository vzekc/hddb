<?php
// Interactive login entry point.  Apache forces the OIDC login for this
// path with redirect semantics; once authenticated, return to the app.

header('Location: ../');
http_response_code(302);
