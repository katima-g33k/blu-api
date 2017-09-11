<?php
$API['member'] = [
  'delete' => function($data) {
    $no = $data['no'];
    $query = "DELETE FROM error WHERE member=$no;
              DELETE FROM item_feed WHERE member=$no;
              DELETE FROM comment WHERE member=$no;
              DELETE FROM phone WHERE member=$no;
              DELETE FROM reservation WHERE member=$no;
              DELETE FROM transaction WHERE member=$no AND type=(SELECT id FROM transaction_type WHERE code='RESERVE');
              DELETE FROM member WHERE no=$no;";
    include '#/connection.php';
    mysqli_multi_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    mysqli_close($connection);
    return OPERATION_SUCCESSFUL;
  },

  'insert' => function($data) {
    $no = insertMember($data);

    foreach($data['phone'] AS $phone) {
      insertPhone($no, $phone['number'], $phone['note']);
    }

    return [ 'no' => $no ];
  },

  'update' => function($data) {
    $id = $data['no'];
    $member = $data['member'];

    deletePhones($id);
    $no = updateMember($id, $member);

    foreach($member['phone'] as $phone) {
      insertPhone($no, $phone['number'], $phone['note']);
    }

    return [ 'no' => $no ];
  },

  'select' => function($data) {
    global $API;

    $member = selectMember($data);
    $no = $member['no'];
    $member['phone'] = selectPhone($no);
    $member['account']['comment'] = $API['member']['selectComment']($no);

    if ($member['firstName'] == 'BLU') {
      $copies = $API['copy']['selectForMember']($no);
      $donations = $API['copy']['selectDonations']();
      $member['account']['copies'] = array_merge($copies, $donations);
    } else {
      $member['account']['copies'] = $API['copy']['selectForMember']($no);
      $member['account']['transfers'] = getTranferDates($no);
    }

    if ($member['isParent']) {
      $member['account']['reservation'] = $API['reservation']['selectForMember']($no);
    }

    return $member;
  },

  'search' => function($data) {
    $search = '%' . str_replace(' ', '%', $data['search']) . '%';
    $deactivated = isset($data['deactivated']) && $data['deactivated'];
    $isParent = isset($data['isParent']) && $data['isParent'];
    $members = [];
    $query = "SELECT no, first_name, last_name, email
              FROM member
              WHERE (CONCAT(first_name, ' ', last_name) LIKE ?
              OR CONCAT(last_name, ' ', first_name) LIKE ?
              OR no LIKE ?
              OR email LIKE ?)";

    if (!$deactivated) {
      $query .= " AND last_activity > SUBDATE(CURRENT_TIMESTAMP, INTERVAL 1 YEAR)";
    }

    if ($isParent) {
      $query .= " AND is_parent=1";
    }


    include '#/connection.php';
    $statement = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($statement, 'ssss', $search, $search, $search, $search);

    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $no, $firstName, $lastName, $email);
    
    while(mysqli_stmt_fetch($statement)) {
      $member = [
        'no' => $no,
        'firstName' => $firstName,
        'lastName' => $lastName,
        'email' => $email
      ];
  
      array_push($members, $member);
    }

    mysqli_stmt_close($statement);
    mysqli_close($connection);
    return $members;
  },

  'insert_comment' => function($data) {
    $no = $data['no'];
    $comment = $data['comment'];
    $employee = 1; // TODO: $data['employee'];
    $query = "INSERT INTO comment(comment, member, updated_at, updated_by)
              VALUES ('$comment', $no, CURRENT_TIMESTAMP, $employee);";

    include '#/connection.php';
    mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    $id = mysqli_insert_id($connection);

    mysqli_close($connection);
    return [ 'id' => $id ];
  },

  'update_comment' => function($data) {
    $id = $data['id'];
    $comment = $data['comment'];
    $employee = 1; // TODO: $data['employee'];
    $query = "UPDATE comment
              SET comment = '$comment',
                  updated_at = CURRENT_TIMESTAMP,
                  updated_by = $employee
              WHERE id = $id;";

    include '#/connection.php';
    mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    mysqli_close($connection);
    return OPERATION_SUCCESSFUL;
  },

  'delete_comment' => function($data) {
    $id = $data['id'];
    $query = "DELETE FROM comment WHERE id = $id;";

    include '#/connection.php';
    mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    mysqli_close($connection);
    return OPERATION_SUCCESSFUL;
  },

  'pay' => function($data) {
    global $API;
    $no = $data['no'];
    $query = "SELECT copy FROM transaction
              WHERE member=$no
              AND (type=(SELECT id FROM transaction_type WHERE code='SELL')
                  OR type=(SELECT id FROM transaction_type WHERE code='SELL_PARENT'))
              AND copy NOT IN(SELECT copy FROM transaction
                            WHERE member=$no
                            AND type=(SELECT id FROM transaction_type WHERE code='PAY'));";

    include '#/connection.php';
    $result = mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    mysqli_close($connection);

    while ($row = mysqli_fetch_assoc($result)) {
      $API['transaction']['insert']([
        'member' => $no,
        'copies' => [ $row['copy'] ],
        'type' => 'PAY'
      ]);
    }

    return OPERATION_SUCCESSFUL;
  },

  'selectComment' => function($no) {
    $comments = [];
    $query = "SELECT id, comment, updated_at, updated_by
              FROM comment
              WHERE member=$no";

    include '#/connection.php';
    $result = mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));

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
  },

  'getName' => function($data) {
    $no = $data['no'];
    $query = "SELECT first_name, last_name FROM member WHERE no=$no";

    include '#/connection.php';
    $result = mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    $row = mysqli_fetch_assoc($result);
    mysqli_close($connection);
    return $row;
  },

  'merge' => function ($data) {
    $no = $data['no'];
    $duplicate = $data['duplicate'];
    $query = "UPDATE transaction SET member=$no WHERE member=$duplicate;
              UPDATE reservation SET member=$no WHERE member=$duplicate;
              UPDATE comment SET member=$no WHERE member=$duplicate;
              UPDATE error SET member=$no WHERE member=$duplicate;
              DELETE FROM item_feed WHERE member=$duplicate;
              DELETE FROM phone WHERE member=$duplicate;
              DELETE FROM member WHERE no=$duplicate;";
  
    include '#/connection.php';
    mysqli_multi_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
  
    mysqli_close($connection);
    return OPERATION_SUCCESSFUL;
  },

  'renew' => function($data) {
    $no = $data['no'];
    $query = "UPDATE member
              SET last_activity = CURRENT_TIMESTAMP
              WHERE no = $no;";

    include '#/connection.php';
    mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    mysqli_close($connection);
    return OPERATION_SUCCESSFUL;
  },

  'exists' => function($data) {
    $query = "SELECT no FROM member WHERE no = ? OR email = ?";
    $no = isset($data['no']) ? $data['no'] : 0;
    $email = isset($data['email']) ? $data['email'] : '';

    include '#/connection.php';
    $statement = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($statement, 'is', $no, $email);

    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $memberNo);
    mysqli_stmt_fetch($statement);

    mysqli_stmt_close($statement);
    mysqli_close($connection);
    return [ 'no' => $memberNo ];
  }
];

function getCityId($city) {
  if ($city['id'] !== 0) {
    return $city['id'];
  }

  $name = $city['name'];
  $state = $city['state']['code'];
  $query = "SELECT id FROM city WHERE name = '$name';";

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
  $row = mysqli_fetch_assoc($result);

  if (isset($row['id'])) {
    mysqli_close($connection);
    return $row['id'];
  }

  $query = "INSERT INTO city(name, state) VALUES ('$name', '$state')";
  mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
  $id = mysqli_insert_id($connection);

  mysqli_close($connection);
  return $id;
}

function insertPhone($member, $number, $note) {
  $query = "INSERT INTO phone(member, number, note)
            VALUES ('$member', '$number', '$note');";

  include '#/connection.php';
  mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
  $id = mysqli_insert_id($connection);

  mysqli_close($connection);
  return $id;
}

function getTranferDates($no) {
  $transfers = [];
  $query = "SELECT DISTINCT(DATE(date)) AS date
            FROM transaction
            WHERE member=$no
            AND type=(SELECT id FROM transaction_type WHERE code='DONATE')
            ORDER BY date ASC;";

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));

  while($row = mysqli_fetch_assoc($result)) {
    array_push($transfers, $row['date']);
  }

  mysqli_close($connection);
  return $transfers;
}

function deletePhones($member) {
  $query = "DELETE FROM phone WHERE member=?;";

  include "#/connection.php";
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'i', $member);

  mysqli_stmt_execute($statement);

  mysqli_stmt_close($statement);
  mysqli_close($connection);
}

function insertMember($data) {
  $query = "INSERT INTO member(no, first_name, last_name, email, is_parent, address, zip, city, registration, last_activity)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);";

  $no = !isset($data['no']) ? fakeMemberNo() : $data['no'];
  $firstName = $data['firstName'];
  $lastName = $data['lastName'];
  $email = $data['email'];
  $isParent = $data['isParent'] ? 1 : 0;
  $address = $data['address'];
  $zip = $data['zip'];
  $city = isset($data['city']) ? getCityId($data['city']) : null;

  include "#/connection.php";
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'isssissi', $no, $firstName, $lastName, $email, $isParent, $address, $zip, $city);

  mysqli_stmt_execute($statement);

  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return $no;
}

function updateMember($id, $member) {
  $query = "UPDATE member
            SET no=?, first_name=?, last_name=?, email=?, is_parent=?, address=?, zip=?, city=?
            WHERE no=?;";

  $no = !isset($member['no']) ? fakeMemberNo() : $member['no'];
  $firstName = $member['firstName'];
  $lastName = $member['lastName'];
  $email = $member['email'];
  $isParent = $member['isParent'] ? 1 : 0;
  $address = $member['address'];
  $zip = $member['zip'];
  $city = isset($member['city']) ? getCityId($member['city']) : null;

  include "#/connection.php";
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'isssissii', $no, $firstName, $lastName, $email, $isParent, $address, $zip, $city, $id);

  mysqli_stmt_execute($statement);
  
  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return $no;
}

function selectMember($data) {
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
            LEFT JOIN city ON member.city = city.id
            LEFT JOIN state ON city.state = state.code
            WHERE ";

  if (isset($data['no'])) {
    $no = $data['no'];
    $query .= "member.no=$no;";
  } else if (isset($data['email'])) {
    $email = $data['email'];
    $query .= "member.email=$email;";
  } else {
    return NO_DATA_FOUND;
  }

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
  $row = mysqli_fetch_assoc($result);
  mysqli_close($connection);

  $isParent = false;

  if ($row['is_parent'] == 1) {
    $isParent = true;
  }

  return [
    'no' => $no,
    'firstName' => $row['first_name'],
    'lastName' => $row['last_name'],
    'email' => $row['email'],
    'isParent' => $isParent,
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
  $result = mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));

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

function fakeMemberNo() {
  include '#/connection.php';

  $query = "SELECT no FROM member WHERE no LIKE '18%' ORDER BY no DESC LIMIT 1";
  $result = mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
  $row = mysqli_fetch_assoc($result);

  mysqli_close($connection);

  if ($row !== null && isset($row['no'])) {
    return  ($row['no'] + 1);
  }

  return 180000000;
}
?>
