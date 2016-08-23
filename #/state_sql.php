<?php
function executeQuery($function, $data) {
  switch ($function) {
    case 'select':
      return select();
  }
}

function select() {
  $states = [];
  $query = "SELECT code FROM state";

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die("Query failed: '$query'");
  mysqli_close($connection);

  while($row = mysqli_fetch_assoc($result)) {
    array_push($states, $row['code']);
  }

  return $states;
}
?>
