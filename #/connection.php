<?php
$error = json_encode([ 'code' => 500, 'message' => 'INTERNAL_SERVER_ERROR']);
$connection = mysqli_connect(URL, USER, PSWD, DB) or die ('Could not connect to server');
mysqli_set_charset($connection, "utf8");
?>
