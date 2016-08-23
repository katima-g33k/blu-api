<?php
function executeQuery($function, $data) {
  switch ($function) {
    case 'insert':
      return insert($data);
    case 'update':
      return update($data['id'], $data['price']);
    case 'delete':
      return delete($data['id']);
  }
}

function insert($data) {
  require_once 'res_sql.php';

  $copyId = insertCopy($data['item_id'], $data['price']);
  insertTransaction($data['member_no'], $copyId, 'ADD');
  renewMember($data['member_no']);

  return ['id' => $copyId];
}

function insertTransaction($member, $copy, $type) {
  $query = "INSERT INTO transaction(type, member, copy, date)
            VALUES ((SELECT id FROM transaction_type WHERE code='$type'), $member, $copy, CURRENT_TIMESTAMP);";

  include '#/connection.php';
  mysqli_query($connection, $query) or die(INTERNAL_SERVER_ERROR);
  mysqli_close($connection);
}

function update($id, $price) {
  $query = "UPDATE copy SET price=$price WHERE id=$id;";

  include '#/connection.php';
  mysqli_query($connection, $query) or die(INTERNAL_SERVER_ERROR);
  mysqli_close($connection);

  return success("UPDATE_SUCCESSFUL");
}

function delete($id) {
  $query = "DELETE FROM transaction WHERE copy=$id;
            DELETE FROM copy WHERE id=$id;";

  include '#/connection.php';
  mysqli_multi_query($connection, $query) or die("Query failed: '$query'");
  mysqli_close($connection);

  return success("DELETE_SUCCESSFUL");
}

function insertCopy($itemId, $price) {
  $query = "INSERT INTO copy(item, price) VALUES ($itemId, $price)";

  include '#/connection.php';
  mysqli_query($connection, $query) or die("Query failed: '$query'");
  $id = mysqli_insert_id($connection);

  mysqli_close($connection);
  return $id;
}

function success($message) {
  return [
    'code' => 200,
    'message' => $message
  ];
}
?>
