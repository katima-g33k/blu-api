<?php
$connection = mysqli_connect(URL, USER, PSWD, DB) or die(INTERNAL_SERVER_ERROR);
mysqli_set_charset($connection, "utf8");
?>
