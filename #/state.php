<?php
$stateList = function() {
  $states = [];
  $query = "SELECT code, name FROM state";

  include "#/connection.php";
  $statement = mysqli_prepare($connection, $query);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $code, $name);

  while (mysqli_stmt_fetch($statement)) {
    array_push($states, [
      'code' => $code,
      'name' => $name
    ]);     
  }
  
  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return $states;
};
?>
