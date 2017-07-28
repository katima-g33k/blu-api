<?php
$API['statistics'] = [
  'byInterval' => function($data) {
    $startDate = $data['startDate'];
    $endDate = $data['endDate'];
    $data = [
      'added' => getByIntervalType($startDate, $endDate, 'ADD'),
      'sold' => getByIntervalType($startDate, $endDate, 'SELL'),
      'soldParent' => getByIntervalType($startDate, $endDate, 'SELL_PARENT'),
      'paid' => getByIntervalType($startDate, $endDate, 'PAY')
    ];

    $data['soldParent']['savings'] = getAmountSaved($startDate, $endDate);

    return $data;
  }
];
?>

<?php
function getByIntervalType($startDate, $endDate, $type) {
    $query = "SELECT COUNT(copy.price) AS quantity, SUM(copy.price) AS amount FROM copy
              INNER JOIN transaction ON copy.id = transaction.copy
              INNER JOIN transaction_type ON transaction.type = transaction_type.id
              WHERE transaction_type.code = '$type'
              AND transaction.date BETWEEN '$startDate' AND '$endDate';";

    include '#/connection.php';
    $result = mysqli_query($connection, $query) or die("Query failed: '$query'");
    $row = mysqli_fetch_assoc($result);

    mysqli_close($connection);

    return [
      'quantity' => $row['quantity'],
      'amount' => $row['amount'],
    ];
}

function getAmountSaved($startDate, $endDate) {
    $query = "SELECT SUM(CEIL(copy.price / 2)) AS savings FROM copy
              INNER JOIN transaction ON copy.id = transaction.copy
              INNER JOIN transaction_type ON transaction.type = transaction_type.id
              WHERE transaction_type.code = 'SELL_PARENT'
              AND transaction.date BETWEEN '$startDate' AND '$endDate';";

    include '#/connection.php';
    $result = mysqli_query($connection, $query) or die("Query failed: '$query'");
    $row = mysqli_fetch_assoc($result);

    mysqli_close($connection);

    return $row['savings'];
}
?>