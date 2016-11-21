<?php
$API['storage'] = [
  'delete' => function($data) {
    $query = "DELETE FROM storage;";
    include '#/connection.php';
    mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    return OPERATION_SUCCESSFUL;
  }
]
?>
