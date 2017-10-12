<?php
// Public API functions
$reservationDelete = function($data = []) {
  $memberNo = $data['member'];
  $itemId = $data['item'];
  if (isset($data['member']) && isset($data['item'])) {
    return deleteReservation($data['member'], $data['item']);
  }

  if (isset($data['from']) && isset($data['to'])) {
    return deleteReservationRange($data['from'], $data['to']);
  }

  return clearReservations();
};

$reservationInsert = function($data = []) {
  $memberNo = $data['member'];
  $itemId = $data['item'];
  $query = "INSERT INTO reservation(member, item, date) VALUES (?,?,CURRENT_TIMESTAMP);";

  include '#/connection.php';
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'ii', $memberNo, $itemId);

  mysqli_stmt_execute($statement);
  $id = mysqli_stmt_insert_id($statement);

  mysqli_close($connection);
  mysqli_stmt_close($statement);
  return [ 'id' => $id ];  
};

$reservationList = function() {
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
  $statement1 = mysqli_prepare($connection, $query);

  mysqli_stmt_execute($statement1);
  mysqli_stmt_bind_result($statement1, $date, $itemId, $itemName, $memberNo, $memberFirstName, $memberLastName);

  while (mysqli_stmt_fetch($statement1)) {
    array_push($reservations, [
      'date' => $date,
      'item' => [
        'id' => $itemId,
        'name' => $itemName
      ],
      'parent' => [
        'no' => $memberNo,
        'firstName' => $memberFirstName,
        'lastName' => $memberLastName
      ]
    ]);
  }

  mysqli_stmt_close($statement1);

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

  $statement2 = mysqli_prepare($connection, $query);
  mysqli_stmt_execute($statement2);
  mysqli_stmt_bind_result($statement2, $date, $copyPrice, $parentNo, $parentFirstName,
                          $parentLastName, $itemId, $itemName, $memberFirstName,
                          $memberLastName, $dateAdded);

  while (mysqli_stmt_fetch($statement2)) {
    array_push($reservations, [
      'date' => $date,
      'parent' => [
        'no' => $parentNo,
        'firstName' => $parentFirstName,
        'lastName' => $parentLastName
      ],
      'item' => [
        'id' => $itemId,
        'name' => $itemName
      ],
      'copy' => [
        'price' => $copyPrice,
        'member' => [
          'firstName' => $memberFirstName,
          'lastName' => $memberLastName
        ],
        'transaction' => [[
          'code' => 'ADD',
          'date' => $dateAdded
        ]]
      ]
    ]);
  }

  mysqli_stmt_close($statement2);  
  mysqli_close($connection);
  return $reservations;
};

// Private ressource functions
function selectReservationsForMember($memberNo) {
  $reservations = [];
  $query = "SELECT reservation.id,
                    reservation.date,
                    item.id,
                    item.name
            FROM reservation
            INNER JOIN item
              ON reservation.item = item.id
            WHERE reservation.member=?;";

  include '#/connection.php';
  $statement1 = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement1, $memberNo);

  mysqli_stmt_execute($statement1);
  mysqli_stmt_bind_result($statement1, $id, $date, $itemId, $itemName);

  while (mysqli_stmt_fetch($statement1)) {
    array_push($reservations, [
      'id' => $id,
      'date' => $date,
      'item' => [
        'id' => $itemId,
        'name' => $itemName
      ]
    ]);
  }

  mysqli_stmt_close($statement1);
  
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

  $statement2 = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement2, $memberNo);

  mysqli_stmt_execute($statement2);
  mysqli_stmt_bind_result($statement2, $id, $date, $copyId, $copyPrice, $itemId,
                          $itemName, $memberNo, $memberFirstName, $memberLastName, $dateAdded);
  

  while (mysqli_stmt_fetch($statement2)) {    
    array_push($reservations, [
      'id' => $id,
      'date' => $date,
      'item' => [
        'id' => $itemId,
        'name' => $itemName
      ],
      'copy' => [
        'id' => $copyId,
        'price' => $copyPrice,
        'member' => [
          'no' => $memberNo,
          'firstName' => $memberFirstName,
          'lastName' => $memberLastName
        ],
        'transaction' => [[
          'code' => 'ADD',
          'date' => $dateAdded
        ]]
      ]
    ]);
  }

  mysqli_stmt_close($statement2);  
  mysqli_close($connection);
  return $reservations;
}

function selectReservationsForItem($itemId) {
  $reservations = [];
  $query = "SELECT reservation.id,
                   reservation.date,
                   member.no,
                   member.first_name,
                   member.last_name
            FROM reservation
            INNER JOIN member
              ON reservation.member=member.no
            WHERE reservation.item=?;";

  include '#/connection.php';
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'i', $itemId);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $id, $date, $memberNo, $memberFirstName, $memberLastName);

  while (mysqli_stmt_fetch($statement)) {
    array_push($reservations, [
      'id' => $id,
      'date' => $date,
      'item' => [
        'id' => $itemId
      ],
      'parent' => [
        'no' => $memberNo,
        'firstName' => $memberFirstName,
        'lastName' => $memberLastName
      ]
    ]);
  }

  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return $reservations;
}

function clearReservations() {
  $query = "DELETE FROM reservation;
            DELETE FROM transaction
            WHERE type=(SELECT id
                        FROM transaction_type
                        WHERE code='RESERVE');";

  include '#/connection.php';
  if (!mysqli_multi_query($connection, $query)) {
    http_response_code(500);
  }

  mysqli_close($connection);
}

function deleteReservation($memberNo, $itemId) {
  $query = "DELETE FROM reservation WHERE member=? AND item=?;";
  
  include '#/connection.php';
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'ii', $memberNo, $itemId);
  mysqli_stmt_execute($statement);

  mysqli_stmt_close($statement);
  mysqli_close($connection);
}

function deleteReservationRange($from, $to) {
  $query = "DELETE FROM reservation WHERE date BETWEEN $from AND $to;
            DELETE FROM transaction
            WHERE type=(SELECT id
                        FROM transaction_type
                        WHERE code='RESERVE'
                        AND date BETWEEN $from AND $to);";

  include '#/connection.php';
  if (!mysqli_multi_query($connection, $query)) {
    http_response_code(500);
  }

  mysqli_close($connection);
}
?>
