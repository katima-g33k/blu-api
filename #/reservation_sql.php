<?php
function executeQuery($function, $data) {
  switch ($function) {
    case 'insert':
      return insert($data['member'], $data['item']);
  }
}

function insert($member, $item) {
  $query = "INSERT INTO reservation(member, item, date)
            VALUES ($member, $item, CURRENT_TIMESTAMP);";

  include '#/connection.php';
  mysqli_query($connection, $query) or die("Query failed: '$query'");
  $id = mysqli_insert_id($connection);

  mysqli_close($connection);
  return [ 'id' => $id ];
}
?>
