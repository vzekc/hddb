<?php
// GET: identity of the logged-in member.  Apache (mod_auth_openidc)
// guarantees authentication and club membership for everything under
// /hddb/auth/; unauthenticated XHR calls receive 401 from Apache.

require_once __DIR__ . '/../../lib/db.php';

json_response(current_user());
