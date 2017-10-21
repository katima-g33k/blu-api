<?php
$storageDelete = function() {
  $query = "DELETE FROM storage;";

  $connection = getConnection();
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_execute($statement);
  
  mysqli_stmt_close($statement);
  mysqli_close($connection);
};

$storageList = function() {
  $storage = [];
  $query = "SELECT storage.no, item.id, item.name
            FROM storage
            INNER JOIN item ON storage.item=item.id;";

  $connection = getConnection();
  $statement = mysqli_prepare($connection, $query);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $no, $id, $name);
  
  while (mysqli_stmt_fetch($statement)) {
    if (!isset($storage[$no])) {
      $storage[$no] = [];
    }

    array_push($storage[$no], [
      'id' => $id,
      'name' => $name
    ]);
  }
  
  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return $storage;
};
?>
