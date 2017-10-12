<?php
// Public API functions
$transactionDelete = function($data = []) {
  $required = ['copy', 'type'];
  foreach($required as $field) {
    if (!isset($data[$field])) {
      http_response_code(400);
      return [ "message" => "Missing parameter '$field'" ];
    }
  }

  deleteTransaction($data['copy'], $data['type']);
};

$transactionInsert = function($data = []) {
  $required = ['copy', 'type', 'member'];
  foreach($required as $field) {
    if (!isset($data[$field])) {
      http_response_code(400);
      return [ "message" => "Missing parameter '$field'" ];
    }
  }

  insertTransaction($data['member'], $data['copy'], $data['type']);
};


// Private ressource functions
function selectTransactions($copy) {
  $transactions = [];
  $query = "SELECT transaction.date,
                    transaction_type.code,
                    member.no,
                    member.first_name,
                    member.last_name
            FROM transaction
            INNER JOIN transaction_type
              ON transaction.type = transaction_type.id
            INNER JOIN member
              ON transaction.member = member.no
            WHERE copy=?;";

  include '#/connection.php';
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'i', $copy);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $date, $code, $memberNo, $memberFirstName, $memberLastName);

  while(mysqli_stmt_fetch($statement)) {
    if ($code == 'TRANSFER_BLU' || $code == 'AJUST_INVENTORY') {
      return false;
    }

    $transaction = [
      'code' => $code,
      'date' => $date
    ];

    if ($code == 'RESERVE') {
      $transaction['parent'] = [
        'no' => $memberNo,
        'firstName' => $memberFirstName,
        'lastName' => $memberLastName
      ];
    }

    array_push($transactions, $transaction);
  }

  mysqli_stmt_close($statement);    
  mysqli_close($connection);
  return $transactions;
};

function insertTransaction($member, $copy, $type) {
  $query = "INSERT INTO transaction(type, member, copy, date)
  VALUES ((SELECT id FROM transaction_type WHERE code=?),?,?,CURRENT_TIMESTAMP);";

  include '#/connection.php';
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'sii', $type, $member, $copy);
  mysqli_stmt_execute($statement);

  mysqli_stmt_close($statement);    
  mysqli_close($connection);

  if ($type == 'SELL_PARENT') {
    deleteTransaction($copy, 'RESERVE');
  }
}

function batchInsertTransactions($member, $copies, $type) {
  $query .= "INSERT INTO transaction(type, member, copy, date)
             VALUES ((SELECT id FROM transaction_type WHERE code='$type'), $member, $copy, CURRENT_TIMESTAMP);";

  include '#/connection.php';
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'sii', $type, $member, $copy);

  foreach($copies as $copy) {
    mysqli_stmt_execute($statement);
  }

  mysqli_stmt_close($statement);
  mysqli_close($connection);

}

function deleteTransaction($copy, $type) {
  include '#/connection.php';

  if ($type == "SELL" || $type == "SELL_PARENT") {
    $query = "DELETE FROM transaction
              WHERE copy=?
              AND (type=(SELECT id FROM transaction_type WHERE code='SELL')
                OR type=(SELECT id FROM transaction_type WHERE code='SELL_PARENT'));";

    $statement = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($statement, 'i', $copyId);  
  } else {
    $query = "DELETE FROM transaction
              WHERE copy=?
              AND type=(SELECT id FROM transaction_type WHERE code=?);";

    $statement = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($statement, 'is', $copyId, $type);  
  }

  mysqli_stmt_execute($statement);
  mysqli_stmt_close($statement);
  mysqli_close($connection);
}
?>
