<?php
$API['copy'] = [
  'selectDonations' => function() {
    global $API;
    $copies = [];
    $query = "SELECT copy.id,
                     copy.item,
                     copy.price,
                     item.name,
                     item.is_book,
                     item.edition,
                     item.editor,
                     status_history.date AS status_date,
                     status.code AS status_code
              FROM copy
              INNER JOIN item
                ON copy.item = item.id
              INNER JOIN status_history
                ON item.id=status_history.item AND item.status=status_history.status
              INNER JOIN status
                ON item.status=status.id
              WHERE copy.id IN (SELECT copy
                                FROM transaction
                                WHERE type=(SELECT id
                                          FROM transaction_type
                                          WHERE code='DONATE'));";

    include '#/connection.php';
    $result = mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    mysqli_close($connection);

    while($row = mysqli_fetch_assoc($result)) {
      $isBook = true;

      if ($row['is_book'] == 0) {
        $isBook = false;
      }

      $copy = [
        'id' => $row['id'],
        'price' => $row['price'],
        'transaction' => $API['transaction']['select']($row['id']),
        'item' => [
          'id' => $row['item'],
          'name' => $row['name'],
          'edition' => $row['edition'],
          'editor' => $row['editor'],
          'is_book' => $isBook,
        ]
      ];

      if ($isBook) {
        $copy['item']['status'] = [];
        $copy['item']['status'][$row['status_code']] = $row['status_date'];
      }

      if ($copy['transaction'] != false) {
        array_push($copies, $copy);
      }
    }

    return $copies;
  },

  'selectForMember' => function($no) {
    global $API;
    $copies = [];
    $query = "SELECT copy.id,
                     copy.item,
                     copy.price,
                     item.name,
                     item.is_book,
                     item.edition,
                     item.editor,
                     status_history.date AS status_date,
                     status.code AS status_code
              FROM copy
              INNER JOIN item
                ON copy.item = item.id
              INNER JOIN status_history
                ON item.id=status_history.item AND item.status=status_history.status
              INNER JOIN status
                ON item.status=status.id
              WHERE copy.id IN (SELECT DISTINCT copy
                                FROM transaction
                                WHERE member = $no)
              AND copy.id NOT IN (SELECT copy
                                  FROM transaction
                                  WHERE member=$no
                                  AND type=(SELECT id
                                            FROM transaction_type
                                            WHERE code='DONATE'))";

    include '#/connection.php';
    $result = mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    mysqli_close($connection);

    while($row = mysqli_fetch_assoc($result)) {
      $isBook = true;

      if ($row['is_book'] == 0) {
        $isBook = false;
      }

      $copy = [
        'id' => $row['id'],
        'price' => $row['price'],
        'transaction' => $API['transaction']['select']($row['id']),
        'item' => [
          'id' => $row['item'],
          'name' => $row['name'],
          'edition' => $row['edition'],
          'editor' => $row['editor'],
          'is_book' => $isBook
        ]
      ];

      if ($isBook) {
        $copy['item']['status'] = [];
        $copy['item']['status'][$row['status_code']] = $row['status_date'];
        $copy['item']['author'] = $API['item']['selectAuthor']($copy['item']['id']);
      }

      if ($copy['transaction'] != false) {
        array_push($copies, $copy);
      }
    }

    return $copies;
  },

  'selectForItem' => function($itemId) {
    global $API;
    $copies = [];
    $query = "SELECT copy.id,
                     copy.price,
                     member.no AS member_no,
                     member.first_name AS member_first_name,
                     member.last_name AS member_last_name,
                     member.last_activity AS member_last_activity
              FROM copy
              INNER JOIN transaction
                ON copy.id = transaction.copy
              INNER JOIN member
                ON transaction.member = member.no
              WHERE copy.item = $itemId
              AND transaction.type = (SELECT id
                                      FROM transaction_type
                                      WHERE code = 'ADD');";

    include '#/connection.php';
    $result = mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    mysqli_close($connection);

    while($row = mysqli_fetch_assoc($result)) {
      $copy = [
        'id' => $row['id'],
        'price' => $row['price'],
        'member' => [
          'no' => $row['member_no'],
          'first_name' => $row['member_first_name'],
          'last_name' => $row['member_last_name'],
          'account'=> [
            'last_activity' => $row['member_last_activity']
          ]
        ],
        'transaction' => $API['transaction']['select']($row['id'])
      ];

      if ($copy['transaction'] != false) {
        array_push($copies, $copy);
      }
    }

    return $copies;
  },

  'insert' => function($data) {
    global $API;

    $id = insertCopy($data['item'], $data['price']);
    $res = [ 'id' => $id ];
    $API['transaction']['_insertOne']($data['member'], $id, 'ADD');
    $API['member']['renew']([ 'no' => $data['member'] ]);

    $reservation = handleReservation($id, $data['item']);

    if ($reservation) {
      $res['reservation'] = $reservation;
    }

    return $res;
  },

  'update' => function($data) {
    $id = $data['id'];
    $price = $data['price'];
    $query = "UPDATE copy SET price=$price WHERE id=$id;";

    include '#/connection.php';
    mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    mysqli_close($connection);

    return OPERATION_SUCCESSFUL;
  },

  'delete' => function($data) {
    $id = $data['id'];
    $query = "DELETE FROM transaction WHERE copy=$id;
              DELETE FROM copy WHERE id=$id;";

    include '#/connection.php';
    mysqli_multi_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    mysqli_close($connection);

    return OPERATION_SUCCESSFUL;
  },

  'getQuantityInStock' => function($itemId) {
    $query = "SELECT
              (SELECT COUNT(DISTINCT(copy.id))
              FROM copy
              INNER JOIN transaction
                ON copy.id = transaction.copy
              WHERE item = ?) - 
              (SELECT COUNT(DISTINCT(copy.id))
              FROM copy
              INNER JOIN transaction
                ON copy.id = transaction.copy
              WHERE item = ?
              AND transaction.type IN (SELECT transaction_type.id
                                      FROM transaction_type
                                      WHERE transaction_type.code
                                      IN ('SELL', 'SELL_PARENT', 'AJUST_INVENTORY')))
            AS inStock;";

    include "#/connection.php";
    $statement = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($statement, 'ii', $itemId, $itemId);

    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $inStock);
    mysqli_stmt_fetch($statement);

    mysqli_stmt_close($statement);
    mysqli_close($connection);
    return $inStock;
  }
];

function insertCopy($itemId, $price) {
  $query = "INSERT INTO copy(item, price) VALUES ($itemId, $price)";

  include '#/connection.php';
  mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
  $id = mysqli_insert_id($connection);

  mysqli_close($connection);
  return $id;
}

function handleReservation($copy, $item) {
  $data = null;
  include '#/connection.php';

  $query = "SELECT r.id,
                   r.member AS no,
                   m.first_name,
                   m.last_name
            FROM reservation r
            INNER JOIN member m
              ON r.member=m.no WHERE item=$item
            ORDER BY date ASC
            LIMIT 1;";
  $result = mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
  $row = mysqli_fetch_assoc($result);

  if ($row != null) {
    $id = $row['id'];
    $memberNo = $row['no'];
    $query = "DELETE FROM reservation WHERE id=$id;
              INSERT INTO transaction(member, copy, date, type)
              VALUES($memberNo,
                     $copy,
                     CURRENT_TIMESTAMP,
                     (SELECT id FROM transaction_type WHERE code='RESERVE')
              );";

    mysqli_multi_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    $data = [
      'no' => $row['no'],
      'first_name' => $row['first_name'],
      'last_name' => $row['last_name'],
    ];
  }

  mysqli_close($connection);
  return $data;
}
?>
