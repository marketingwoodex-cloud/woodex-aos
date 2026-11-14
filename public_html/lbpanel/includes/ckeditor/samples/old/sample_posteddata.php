<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/../../../../config/auth_guard.php';
}
 /* <body><pre>

-------------------------------------------------------------------------------------------
  CKEditor - Posted Data

  We are sorry, but your Web server does not support the PHP language used in this script.

  Please note that CKEditor can be used with any other server-side language than just PHP.
  To save the content created with CKEditor you need to read the POST data on the server
  side and write it to a file or the database.

  Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
  CKEditor 4 LTS ("Long Term Support") is available under the terms of the Extended Support Model.
-------------------------------------------------------------------------------------------

</pre><div style="display:none"></body> */ include "assets/posteddata.php"; ?>
