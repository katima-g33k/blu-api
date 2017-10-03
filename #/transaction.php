<?php
$API['transaction'] = [
  'delete' => function($data) {
    $copyId = $data['copy'];
    $type = $data['type'];
    $query = "DELETE FROM transaction
              WHERE copy=$copyId
              AND type=(SELECT id FROM transaction_type WHERE code='$type');";

    if ($type == "SELL" || $type == "SELL_PARENT") {
      $query = "DELETE FROM transaction
                WHERE copy=$copyId
                AND (type=(SELECT id FROM transaction_type WHERE code='SELL')
                  OR type=(SELECT id FROM transaction_type WHERE code='SELL_PARENT'));";
    }

    include '#/connection.php';
    mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    mysqli_close($connection);

    return OPERATION_SUCCESSFUL ;
  },

  'insert' => function($data) {
    global $API;

    $member = $data['member'];
    $copies = $data['copies'];
    $type = $data['type'];

    foreach($copies as $copy) {
      $API['transaction']['_insertOne']($member, $copy, $type);
    }

    // If adding new books or getting money back
    if ($type == "ADD" || $type == "PAY") {
      $API['member']['renew']([ 'no' => $member ]);
    }

    return OPERATION_SUCCESSFUL;
  },

  'select' => function($copy) {
    $transactions = [];
    $query = "SELECT transaction.date,
                     transaction_type.code,
                     member.no as member_no,
                     member.first_name as member_first_name,
                     member.last_name as member_last_name
              FROM transaction
              INNER JOIN transaction_type
                ON transaction.type = transaction_type.id
              INNER JOIN member
                ON transaction.member = member.no
              WHERE copy = $copy;";

    include '#/connection.php';
    $result = mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));

    while($row = mysqli_fetch_assoc($result)) {
      if ($row['code'] == 'TRANSFER_BLU' || $row['code'] == 'AJUST_INVENTORY') {
        return false;
      }

      $transaction = [
        'code' => $row['code'],
        'date' => $row['date']
      ];

      if ($row['code'] == 'RESERVE') {
        $transaction['parent'] = [
          'no' => $row['member_no'],
          'firstName' => $row['member_first_name'],
          'lastName' => $row['member_last_name']
        ];
      }

      array_push($transactions, $transaction);
    }

    mysqli_close($connection);
    return $transactions;
  },

  '_insertOne' => function($member, $copy, $type) {
    global $API;
    $query = "INSERT INTO transaction(type, member, copy, date)
              VALUES ((SELECT id FROM transaction_type WHERE code='$type'), $member, $copy, CURRENT_TIMESTAMP);";

    include '#/connection.php';
    mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    mysqli_close($connection);

    if ($type == "SELL_PARENT") {
      $data = [
        'copy' => $copy,
        'type' => 'RESERVE'
      ];
      $API['transaction']['delete']($data);
    }
  }
];
?>
