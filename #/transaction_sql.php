<?php
function executeQuery($function, $data) {
  switch ($function) {
    case 'insert':
      return insert($data['member'], $data['copies'], $data['type']);
    case 'delete':
      return delete($data['copy'], $data['type']);
  }
}

function insert($member, $copies, $type) {
  foreach($copies as $copy) {
    insertOne($member, $copy, $type);
  }

  // If adding new books or getting money back
  if ($type == "ADD" || $type == "PAY") {
    require_once 'res_sql.php';
    renewMember($member);
  }

  return [
    'code' => 200,
    'message' => 'TRANSACTIONS_SUCCESFULLY_INSERTED'
  ];
}

function insertOne($member, $copy, $type) {
  $query = "INSERT INTO transaction(type, member, copy, date)
            VALUES ((SELECT id FROM transaction_type WHERE code=\"$type\"), $member, $copy, CURRENT_TIMESTAMP);";

  include '#/connection.php';
  mysqli_query($connection, $query) or die(INTERNAL_SERVER_ERROR);
  mysqli_close($connection);
}

function delete($copyId, $type) {
  $query = "DELETE FROM transaction WHERE copy=$copyId AND type=(SELECT id FROM transaction_type WHERE code=\"$type\");";

  if ($type == "SELL" || $type == "SELL_PARENT") {
    $query = "DELETE FROM transaction
              WHERE copy=$copyId
              AND (type=(SELECT id FROM transaction_type WHERE code=\"SELL\")
                OR type=(SELECT id FROM transaction_type WHERE code=\"SELL_PARENT\"));";
  }

  include '#/connection.php';
  mysqli_query($connection, $query) or die(INTERNAL_SERVER_ERROR);
  mysqli_close($connection);

  return [ 'code' => 200, 'message' => 'DELETE_SUCCESSFUL' ];
}
?>
