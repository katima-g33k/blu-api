<?php
$intervalStatistics = function($data = []) {
  if (!isset($data['startDate']) || !isset($data['endDate'])) {
    http_response_code(401);  
    return [ 'message' => 'Must provide valid parameters \'startDate\' and \'endDate\'' ];    
  }

  $startDate = $data['startDate'];
  $endDate = $data['endDate'];
  $stats = [
    'added' => getByIntervalType($startDate, $endDate, 'ADD'),
    'sold' => getByIntervalType($startDate, $endDate, 'SELL'),
    'soldParent' => getByIntervalType($startDate, $endDate, 'SELL_PARENT'),
    'paid' => getByIntervalType($startDate, $endDate, 'PAY')
  ];

  $stats['soldParent']['savings'] = getAmountSaved($startDate, $endDate);

  return $stats;
};

// Private ressource functions
function getByIntervalType($startDate, $endDate, $type) {
  $query = "SELECT COUNT(copy.price), SUM(copy.price) FROM copy
            INNER JOIN transaction ON copy.id = transaction.copy
            INNER JOIN transaction_type ON transaction.type = transaction_type.id
            WHERE transaction_type.code=?
            AND transaction.date BETWEEN ? AND ?;";

  include "#/connection.php";
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'sss', $type, $startDate, $endDate);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $quantity, $amount);
  mysqli_stmt_fetch($statement);
  
  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return [
    'quantity' => $quantity,
    'amount' => $amount,
  ];
}

function getAmountSaved($startDate, $endDate) {
  $query = "SELECT SUM(CEIL(copy.price / 2)) FROM copy
            INNER JOIN transaction ON copy.id = transaction.copy
            INNER JOIN transaction_type ON transaction.type = transaction_type.id
            WHERE transaction_type.code = 'SELL_PARENT'
            AND transaction.date BETWEEN ? AND ?;";

  include "#/connection.php";
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'ss', $startDate, $endDate);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $savings);
  mysqli_stmt_fetch($statement);
  
  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return $savings;
}
?>