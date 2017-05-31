<?php
$API['reservation'] = [
  'delete' => function($data) {
    $memberNo = $data['member'];
    $itemId = $data['item'];

    $query = "DELETE FROM reservation WHERE member=$memberNo AND item=$itemId;";
    include '#/connection.php';
    mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    return OPERATION_SUCCESSFUL;
  },

  'deleteAll' => function($data) {
    $query = "DELETE FROM reservation;
              DELETE FROM transaction
              WHERE type=(SELECT id
                          FROM transaction_type
                          WHERE code='RESERVE');";
    include '#/connection.php';
    mysqli_multi_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    return OPERATION_SUCCESSFUL;
  },

  'deleteRange' => function($data) {
    $from = $data['from'];
    $to = $data['to'];
    $query = "DELETE FROM reservation WHERE date BETWEEN $from AND $to;
              DELETE FROM transaction
              WHERE type=(SELECT id
                          FROM transaction_type
                          WHERE code='RESERVE'
                          AND date BETWEEN $from AND $to);";
    include '#/connection.php';
    mysqli_multi_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    return OPERATION_SUCCESSFUL;
  },

  'insert' => function($data) {
    $member = $data['member'];
    $item = $data['item'];
    $query = "INSERT INTO reservation(member, item, date)
              VALUES ($member, $item, CURRENT_TIMESTAMP);";

    include '#/connection.php';
    mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    $data = [ 'id' => mysqli_insert_id($connection) ];

    mysqli_close($connection);
    return array_merge(OPERATION_SUCCESSFUL, $data);
  },

  'select' => function($data) {
    $reservations = [];
    $query = "SELECT reservation.date,
                     item.id AS item_id,
                     item.name AS item_name,
                     member.no AS member_no,
                     member.first_name AS member_first_name,
                     member.last_name AS member_last_name
              FROM reservation
              INNER JOIN member
                ON reservation.member=member.no
              INNER JOIN item
                ON reservation.item=item.id;";

    include '#/connection.php';
    $result = mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));

    while ($row = mysqli_fetch_assoc($result)) {
      $reservation = [
        'date' => $row['date'],
        'item' => [
          'id' => $row['item_id'],
          'name' => $row['item_name']
        ],
        'parent' => [
          'no' => $row['member_no'],
          'first_name' => $row['member_first_name'],
          'last_name' => $row['member_last_name']
        ]
      ];
      array_push($reservations, $reservation);
    }

    $query = "SELECT t.date,
                     c.price AS copy_price,
                     p.no AS parent_no,
                     p.first_name AS parent_first_name,
                     p.last_name AS parent_last_name,
                     i.id AS item_id,
                     i.name AS item_name,
                     m.member_first_name,
                     m.member_last_name,
                     m.date_added
              FROM transaction t
              INNER JOIN copy c
                ON t.copy=c.id
              INNER JOIN member p
                ON t.member=p.no
              INNER JOIN item i
                ON c.item=i.id
              INNER JOIN (SELECT m.no AS member_no,
                                 m.first_name AS member_first_name,
                                 m.last_name AS member_last_name,
                                 transaction.date AS date_added,
                                 copy
                          FROM transaction
                          INNER JOIN member m
                            ON transaction.member=m.no
                          WHERE type=(SELECT id
                                      FROM transaction_type
                                      WHERE code='ADD')
              ) m
                ON c.id=m.copy
              WHERE t.type=(SELECT id
                            FROM transaction_type
                            WHERE code='RESERVE');";

    $result = mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    while ($row = mysqli_fetch_assoc($result)) {
      $reservation = [
        'date' => $row['date'],
        'parent' => [
          'no' => $row['parent_no'],
          'first_name' => $row['parent_first_name'],
          'last_name' => $row['parent_last_name']
        ],
        'item' => [
          'id' => $row['item_id'],
          'name' => $row['item_name']
        ],
        'copy' => [
          'price' => $row['copy_price'],
          'member' => [
            'first_name' => $row['member_first_name'],
            'last_name' => $row['member_last_name']
          ],
          'transaction' => [[
            'code' => 'ADD',
            'date' => $row['date_added']
          ]]
        ]
      ];
      array_push($reservations, $reservation);
    }

    mysqli_close($connection);
    return $reservations;
  },

  'selectForItem' => function($itemId) {
    $reservations = [];
    $query = "SELECT reservation.id,
                     reservation.date,
                     member.no AS member_no,
                     member.first_name AS member_first_name,
                     member.last_name AS member_last_name
              FROM reservation
              INNER JOIN member
                ON reservation.member=member.no
              WHERE reservation.item=$itemId;";

    include '#/connection.php';
    $result = mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    mysqli_close($connection);

    while ($row = mysqli_fetch_assoc($result)) {
      $reservation = [
        'id' => $row['id'],
        'date' => $row['date'],
        'item' => [
          'id' => $itemId
        ],
        'parent' => [
          'no' => $row['member_no'],
          'first_name' => $row['member_first_name'],
          'last_name' => $row['member_last_name']
        ]
      ];
      array_push($reservations, $reservation);
    }


    return $reservations;
  },

  'selectForMember' => function($memberNo) {
    $reservations = [];
    $query = "SELECT reservation.id,
                     reservation.date,
                     item.id AS item_id,
                     item.name AS item_name
              FROM reservation
              INNER JOIN item
                ON reservation.item = item.id
              WHERE reservation.member = $memberNo;";

    include '#/connection.php';

    $result = mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    while ($row = mysqli_fetch_assoc($result)) {
      $reservation = [
        'id' => $row['id'],
        'date' => $row['date'],
        'item' => [
          'id' => $row['item_id'],
          'name' => $row['item_name']
        ]
      ];
      array_push($reservations, $reservation);
    }

    $query = "SELECT t.id,
                     t.date,
                     c.id AS copy_id,
                     c.price AS copy_price,
                     i.id AS item_id,
                     i.name AS item_name,
                     m.member_no,
                     m.member_first_name,
                     m.member_last_name,
                     m.date_added
              FROM transaction t
              INNER JOIN copy c
                ON t.copy=c.id
              INNER JOIN item i
                ON c.item=i.id
              INNER JOIN (SELECT m.no AS member_no,
                                 m.first_name AS member_first_name,
                                 m.last_name AS member_last_name,
                                 transaction.date AS date_added,
                                 copy
                          FROM transaction
                          INNER JOIN member m
                            ON transaction.member=m.no
                          WHERE type=(SELECT id
                                      FROM transaction_type
                                      WHERE code='ADD')
              ) m
                ON c.id=m.copy
              WHERE t.member=$memberNo
              AND t.type=(SELECT id
                          FROM transaction_type
                          WHERE code='RESERVE');";

    $result = mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    while ($row = mysqli_fetch_assoc($result)) {
      $reservation = [
        'id' => $row['id'],
        'date' => $row['date'],
        'item' => [
          'id' => $row['item_id'],
          'name' => $row['item_name']
        ],
        'copy' => [
          'id' => $row['copy_id'],
          'price' => $row['copy_price'],
          'member' => [
            'no' => $row['member_no'],
            'first_name' => $row['member_first_name'],
            'last_name' => $row['member_last_name']
          ],
          'transaction' => [[
            'code' => 'ADD',
            'date' => $row['date_added']
          ]]
        ]
      ];

      array_push($reservations, $reservation);
    }

    mysqli_close($connection);
    return $reservations;
  }
];
?>
