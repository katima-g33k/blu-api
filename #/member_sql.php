<?php
require_once 'index.php';

function executeQuery($function, $data) {
  switch ($function) {
    case 'insert':
      return insert($data);
    case 'update':
      return update($data['no'], $data['member']);
    case 'delete':
      return delete($data['no']);
    case 'select':
      return select($data['no']);
    case 'search':
      return search($data['search'], isset($data['deactivated']));
    case 'renew':
      return renew($data['no']);
    case 'insert_comment':
      return insertComment($data['no'], $data['comment'], 1);
    case 'update_comment':
      return updateComment($data['id'], $data['comment'], 1);
    case 'delete_comment':
      return deleteComment($data['id']);
    case 'exist':
      return exist($data['no']);
    case 'pay':
      return pay($data['no']);
  }
}

function insert($data) {
  $phones = [];
  $response = insertMember($data);

  foreach($data['phone'] AS $phone) {
    $id = insertPhone($data['no'], $phone['number'], $phone['note']);
    array_push($phones, ['id' => $id, 'number' => $phone['number']]);
  }

  if (count($phones) > 0) {
    $response['phone'] = $phones;
  }

  if (isset($data['comment'])) {
    $id = insertComment($data['no'], $data['comment']);
    $response['comment'] = [ 'id' => $id ];
  }

  return $response;
}

function insertMember($data) {
  $no = $data['no'];
  $firstName = $data['first_name'];
  $lastName = $data['last_name'];
  $email = $data['email'];
  $isParent = 0;
  $address = $data['address'];
  $zip = $data['zip'];
  $city = getCityId($data['city'], $data['state']);

  if (isset($data['is_parent']) && $data['is_parent']) {
    $isParent = 1;
  }

  $query = "INSERT INTO member(no, first_name, last_name, email, registration, last_activity,
                               is_parent, address, zip, city)
            VALUES ('$no', '$firstName', '$lastName', '$email', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP,
                    $isParent, '$address', '$zip', $city);";

  include '#/connection.php';
  mysqli_query($connection, $query) or die("Query failed: '$query'");
  mysqli_close($connection);

  if ($city > 0) {
    return [ 'city' => ['id' => $city ]];
  }

  return [];
}

function getCityId($city, $state) {
  $query = "SELECT id FROM city WHERE name = '$city';";

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die("Query failed: '$query'");
  $row = mysqli_fetch_assoc($result);

  if (isset($row['id'])) {
    mysqli_close($connection);
    return $row['id'];
  }

  $query = "INSERT INTO city(name, state) VALUES ('$city', '$state')";
  mysqli_query($connection, $query) or die("Query failed: '$query'");
  $id = mysqli_insert_id($connection);

  mysqli_close($connection);
  return $id;
}

function insertComment($no, $comment, $employee) {
  $query = "INSERT INTO comment(comment, member, updated_at, updated_by)
            VALUES ('$comment', $no, CURRENT_TIMESTAMP, $employee);";

  include '#/connection.php';
  mysqli_query($connection, $query) or die("Query failed: '$query'");
  $id = mysqli_insert_id($connection);

  mysqli_close($connection);
  return [ 'id' => $id ];
}

function insertPhone($member, $number, $note) {
  $query = "INSERT INTO phone(member, number, note)
            VALUES ('$member', '$number', '$note');";

  include '#/connection.php';
  mysqli_query($connection, $query) or die("Query failed: '$query'");
  $id = mysqli_insert_id($connection);

  mysqli_close($connection);
  return $id;
}

function deletePhone($id) {
  $query = "DELETE FROM phone WHERE id=$id;";

  include '#/connection.php';
  mysqli_query($connection, $query) or die("Query failed: '$query'");
  mysqli_close($connection);
}

function update($no, $member) {
  $phones = [];
  $response = updateMember($no, $member);

  if (isset($member['phone'])) {
    foreach($member['phone'] AS $phone) {
      if (isset($phone['id']) && isset($phone['number'])) {
        updatePhone($phone['id'], $phone['number'], $phone['note']);
      } elseif (isset($phone['id'])) {
        deletePhone($phone['id']);
        array_push($phones, ['id' => $phone['id']]);
      } else {
        $id = insertPhone($no, $phone['number'], $phone['note']);
        array_push($phones, ['id' => $id, 'number' => $phone['number']]);
      }
    }

    if (count($phones) > 0) {
      $response['phone'] = $phones;
    }
  }

  return $response;
}

function updateMember($no, $member) {
  $hasCity = false;
  $set = "";
  foreach ($member as $key => $value) {
    if ($key == 'is_parent') {
      if ($value) {
        $set .= "$key = 1 ";
      } else {
        $set .= "$key = 0 ";
      }
    } elseif (($key == 'city' || $key == 'state') && !$hasCity) {
      $hasCity = true;
      $city = getCityId($member['city'], $member['state']);
      $set .= "city = $city";
    } else {
      $set .= "$key = '$value'";
    }
  }

  $query = "UPDATE member SET $set WHERE no = '$no';";

  include '#/connection.php';
  mysqli_query($connection, $query) or die(INTERNAL_SERVER_ERROR);
  mysqli_close($connection);

  return success("UPDATE_SUCCESSFUL");
}

function updatePhone($id, $number, $note) {
  $query = "UPDATE phone SET number = '$number'
                             note = '$note'
            WHERE id = '$id';";

  include '#/connection.php';
  mysqli_query($connection, $query) or die("Query failed: '$query'");
  mysqli_close($connection);
}

function search($search, $deactivated) {
  $members = [];
  $query = "SELECT no, first_name, last_name
            FROM member
            WHERE (";

  $searchValues = explode(" ", $search);

  foreach ($searchValues as $index => $value) {
    if ($index != 0) {
      $query .= " OR ";
    }

    $query .= "no LIKE '%$value%'
              OR first_name LIKE '%$value%'
              OR last_name LIKE '%$value%'";
  }

  $query .= ")";

  if (!$deactivated) {
    $query .= " AND last_activity > SUBDATE(CURRENT_TIMESTAMP, INTERVAL 1 YEAR)";
  }

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die("Query failed: '$query'");

  while($row = mysqli_fetch_assoc($result)) {
    $member = [
      'last_name' => $row['last_name'],
      'first_name' => $row['first_name'],
      'no' => $row['no']
    ];

    array_push($members, $member);
  }

  mysqli_close($connection);
  return $members;
}

function updateComment($id, $comment, $employee) {
  $query = "UPDATE comment
            SET comment = '$comment',
                updated_at = CURRENT_TIMESTAMP,
                updated_by = $employee
            WHERE id = $id;";

  include '#/connection.php';
  mysqli_query($connection, $query) or die("Query failed: '$query'");
  mysqli_close($connection);
  return success("UPDATE_SUCCESSFUL");
}

function deleteComment($id) {
  $query = "DELETE FROM comment WHERE id = $id;";

  include '#/connection.php';
  mysqli_query($connection, $query) or die("Query failed: '$query'");
  mysqli_close($connection);
  return success("UPDATE_SUCCESSFUL");
}

function select($no) {
  $member = selectMember($no);
  $member['phone'] = selectPhone($no);
  $member['account']['comment'] = selectComment($no);
  $member['account']['copies'] = selectCopies($no);

  if ($member['is_parent']) {
    $member['reservation'] = selectReservation($no);
  }

  return $member;
}

function selectMember($no) {
  $query = "SELECT first_name,
                   last_name,
                   email,
                   address,
                   zip,
                   is_parent,
                   registration,
                   last_activity,
                   city.id AS city_id,
                   city.name AS city_name,
                   state.code AS state_code,
                   state.name AS state_name
            FROM member
            INNER JOIN city ON member.city = city.id
            INNER JOIN state ON city.state = state.code
            WHERE member.no = $no";

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die("Query failed: '$query'");
  $row = mysqli_fetch_assoc($result);
  mysqli_close($connection);

  $isParent = false;

  if ($row['is_parent'] == 1) {
    $isParent = true;
  }

  return [
    'no' => $no,
    'first_name' => $row['first_name'],
    'last_name' => $row['last_name'],
    'email' => $row['email'],
    'is_parent' => $isParent,
    'address' => $row['address'],
    'zip' => $row['zip'],
    'city' => [
      'id' => $row['city_id'],
      'name' => $row['city_name'],
      'state' => [
        'code' => $row['state_code'],
        'name' => $row['state_name']
      ]
    ],
    'account' => [
      'registration' => $row['registration'],
      'last_activity' => $row['last_activity'],
    ]
  ];
}

function selectPhone($no) {
  $phones = [];
  $query = "SELECT id, number, note
            FROM phone
            WHERE member=$no";

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die("Query failed: '$query'");

  while($row = mysqli_fetch_assoc($result)) {
    $phone = [
      'id' => $row['id'],
      'number' => $row['number']
    ];

    if ($row['note'] != null) {
      $phone['note'] = $row['note'];
    }

    array_push($phones, $phone);
  }

  mysqli_close($connection);
  return $phones;
}

function selectComment($no) {
  $comments = [];
  $query = "SELECT id, comment, updated_at, updated_by
            FROM comment
            WHERE member=$no";

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die("Query failed: '$query'");

  while($row = mysqli_fetch_assoc($result)) {
    $comment = [
      'id' => $row['id'],
      'comment' => $row['comment'],
      'updated_at' => $row['updated_at'],
      'updated_by' => $row['updated_by']
    ];

    array_push($comments, $comment);
  }

  mysqli_close($connection);
  return $comments;
}

function selectCopies($no) {
  $copies = [];
  $query = "SELECT copy.id,
                   copy.item,
                   copy.price,
                   item.name,
                   item.is_book,
                   item.edition,
                   item.editor
            FROM copy
            INNER JOIN item ON copy.item = item.id
            WHERE copy.id IN (SELECT DISTINCT copy
                              FROM transaction
                              WHERE member = $no)";

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die(INTERNAL_SERVER_ERROR);
  mysqli_close($connection);

  require_once 'res_sql.php';
  while($row = mysqli_fetch_assoc($result)) {
    $isBook = true;

    if ($row['is_book'] == 0) {
      $isBook = false;
    }

    $copy = [
      'id' => $row['id'],
      'price' => $row['price'],
      'transaction' => getCopyTransactions($row['id']),
      'item' => [
        'id' => $row['item'],
        'name' => $row['name'],
        'edition' => $row['edition'],
        'editor' => $row['editor'],
        'is_book' => $isBook
      ]
    ];

    if ($copy['transaction'] != false) {
      array_push($copies, $copy);
    }
  }

  return $copies;
}

function selectReservation($no) {
  $reservations = [];
  $query = "SELECT reservation.date AS date,
                   reservation.item AS item_id,
                   item.name AS item_name
            FROM reservation
            INNER JOIN item
              ON reservation.item = item.id
            WHERE reservation.member = $no;";

  include '#/connection.php';
  mysqli_query($connection, $query) or die(INTERNAL_SERVER_ERROR);

  while($row = mysqli_fetch_assoc($result)) {
    $reservation = [
      'date' => $row['date'],
      'item' => [
        'id' => $row['item_id'],
        'name' => $row['item_name']
      ]
    ];

    array_push($reservations, $reservation);
  }

  mysqli_close($connection);
  return $reservations;
}

function delete($no) {
  $query = "DELETE FROM member WHERE no=$no";
  include '#/connection.php';
  mysqli_query($connection, $query) or die(INTERNAL_SERVER_ERROR);
  mysqli_close($connection);
  return success("DELETE_SUCCESSFUL");
}

function renew($no) {
  require_once 'res_sql.php';
  renewMember($no);
  return success("RENEW_SUCCESSFUL");
}

function exist($no) {
  $query = "SELECT COUNT(id) AS count FROM member WHERE no = $no";

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die(INTERNAL_SERVER_ERROR);
  $row = mysqli_fetch_assoc($result);
  $count = $row['count'];
  mysqli_close($connection);

  if ($count > 0) {
    return response(200, "DATA_FOUND");
  }

  return response(422, "NO_DATA_FOUND");
}

function pay($no) {
  $copies = [];
  $query = "SELECT copy FROM transaction
            WHERE member=$no
            AND (type=(SELECT id FROM transaction_type WHERE code='SELL')
                OR type=(SELECT id FROM transaction_type WHERE code='SELL_PARENT'))
            AND copy NOT IN(SELECT copy FROM transaction
                          WHERE member=$no
                          AND type=(SELECT id FROM transaction_type WHERE code='PAY'));";

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die(INTERNAL_SERVER_ERROR);
  mysqli_close($connection);

  while ($row = mysqli_fetch_assoc($result)) {
    require_once 'res_sql.php';
    insertTransaction($no, $row['copy'], "PAY");
  }

  return success('INSERT_SUCCESSFUL');
}

function success($message) {
  return [
    'code' => 200,
    'message' => $message
  ];
}

function response($code, $message) {
  return [
    'code' => $code,
    'message' => $message
  ];
}
?>
