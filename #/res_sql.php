<?php
function getCopyTransactions($copy) {
  $transactions = [];
  $query = "SELECT transaction.date,
                   transaction_type.code
            FROM transaction
            INNER JOIN transaction_type
              ON transaction.type = transaction_type.id
            WHERE copy = $copy;";

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die(INTERNAL_SERVER_ERROR);

  while($row = mysqli_fetch_assoc($result)) {
    if ($row['code'] == 'TRANSFER_BLU' || $row['code'] == 'AJUST_INVENTORY') {
      return false;
    }

    $transaction = [
      'code' => $row['code'],
      'date' => $row['date']
    ];

    array_push($transactions, $transaction);
  }

  mysqli_close($connection);
  return $transactions;
}

function renewMember($no) {
  $query = "UPDATE member
            SET last_activity = CURRENT_TIMESTAMP
            WHERE no = $no";

  include '#/connection.php';
  mysqli_query($connection, $query) or die(INTERNAL_SERVER_ERROR);
  mysqli_close($connection);
}

function insertTransaction($member, $copy, $type) {
  $query = "INSERT INTO transaction(type, member, copy, date)
            VALUES ((SELECT id FROM transaction_type WHERE code='$type'), $member, $copy, CURRENT_TIMESTAMP);";

  include '#/connection.php';
  mysqli_query($connection, $query) or die(INTERNAL_SERVER_ERROR);
  mysqli_close($connection);
}
?>
