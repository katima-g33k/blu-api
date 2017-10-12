<?php
$connection = mysqli_connect(URL, USER, PSWD, DB);

if (!$connection) {
  http_response_code(500);
  return;
}

mysqli_set_charset($connection, "utf8");
?>
