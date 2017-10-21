<?php
// Public API functions
$transactionDelete = function($params, $data = []) {
  if (!isset($data['type'])) {
    http_response_code(400);
    return [ "message" => "Missing parameter 'type'" ];
  }

  deleteTransaction($params['copyId'], $data['type']);
};

$transactionInsert = function($params, $data = []) {
  if (!isset($data['type'])) {
    http_response_code(400);
    return [ "message" => "Missing parameter 'type'" ];
  }
  insertTransaction($params['memberNo'], $params['copyId'], $data['type']);
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

  $connection = getConnection();
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

  $connection = getConnection();
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
  $query = "INSERT INTO transaction(type, member, copy, date)
             VALUES ((SELECT id FROM transaction_type WHERE code=?), ?, ?, CURRENT_TIMESTAMP);";

  $connection = getConnection();
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'sii', $type, $member, $copy);

  foreach($copies as $copy) {
    mysqli_stmt_execute($statement);
  }

  mysqli_stmt_close($statement);
  mysqli_close($connection);

}

function deleteTransaction($copy, $type) {
  $type = $type == "SELL_PARENT" ? "SELL%" : ($type . "%");
  $query = "DELETE FROM transaction
            WHERE copy=?
            AND type IN (SELECT id FROM transaction_type WHERE code LIKE ?);";

  $connection = getConnection();
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'is', $copy, $type);

  mysqli_stmt_execute($statement);
  mysqli_stmt_close($statement);
  mysqli_close($connection);
}
?>
