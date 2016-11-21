<?php
$API['member'] = [
  'delete' => function($data) {
    $no = $data['no'];
    $query = "DELETE FROM comment WHERE member=$no;
              DELETE FROM phone WHERE member=$no;
              DELETE FROM member WHERE no=$no;";
    include '#/connection.php';
    mysqli_multi_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    mysqli_close($connection);
    return OPERATION_SUCCESSFUL;
  },

  'insert' => function($data) {
    global $API;
    $phones = [];
    $response = $API['member']['insertMember']($data);

    foreach($data['phone'] AS $phone) {
      $id = insertPhone($data['no'], $phone['number'], $phone['note']);
      array_push($phones, ['id' => $id, 'number' => $phone['number']]);
    }

    if (count($phones) > 0) {
      $response['phone'] = $phones;
    }

    if (isset($data['account']) && isset($data['account']['comment'])) {
      $comment = $data['account']['comment'][0]['comment'];
      $response['comment'] = $API['member']['insert_comment'](['no' => $data['no'], 'comment' => $comment ]);;
    }

    return $response;
  },

  'update' => function($data) {
    $no = $data['no'];
    $member = $data['member'];
    $phones = [];
    $response = updateMember($no, $member);

    if (isset($member['phone'])) {
      foreach($member['phone'] as $phone) {
        if (isset($phone['id']) && isset($phone['number'])) {
          updatePhone($phone['id'], $phone['number'], $phone['note']);
        } else if (isset($phone['id'])) {
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
  },

  'select' => function($data) {
    global $API;

    $member = selectMember($data);
    $no = $member['no'];
    $member['phone'] = selectPhone($no);
    $member['account']['comment'] = $API['member']['selectComment']($no);

    if ($member['first_name'] == 'BLU') {
      $copies = $API['copy']['selectForMember']($no);
      $donations = $API['copy']['selectDonations']();
      $member['account']['copies'] = array_merge($copies, $donations);
    } else {
      $member['account']['copies'] = $API['copy']['selectForMember']($no);
    }

    if ($member['is_parent']) {
      $member['account']['reservation'] = $API['reservation']['selectForMember']($no);
    }

    return $member;
  },

  'search' => function($data) {
    $search = $data['search'];
    $deactivated = isset($data['deactivated']) && $data['deactivated'];
    $isParent = isset($data['is_parent']) && $data['is_parent'];
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

    if ($isParent) {
      $query .= " AND is_parent=1";
    }

    include '#/connection.php';
    $result = mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));

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

  'insertMember' => function($data) {
    global $API;

    $no = $data['no'];
    $firstName = $data['first_name'];
    $lastName = $data['last_name'];
    $email = $data['email'];
    $isParent = 0;
    $address = $data['address'];
    $zip = $data['zip'];
    $city = getCityId($data['city']);

    if (isset($data['is_parent']) && $data['is_parent']) {
      $isParent = 1;
    }

    $query = "INSERT INTO member(no, first_name, last_name, email, registration, last_activity,
                                 is_parent, address, zip, city)
              VALUES ('$no', '$firstName', '$lastName', '$email', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP,
                      $isParent, '$address', '$zip', $city);";

    include '#/connection.php';
    mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    mysqli_close($connection);

    if ($city > 0) {
      return [ 'city' => ['id' => $city ]];
    }

    return [];
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

  'exist' => function($data) {
    $query = "SELECT COUNT(no) AS count FROM member WHERE ";

    if (isset($data['no'])) {
      $query .= "no=" . $data['no'] . ";";
    } else if (isset($data['email'])) {
      $query .= "email='" . $data['email'] . "';";
    } else {
      return NO_DATA_FOUND;
    }

    include '#/connection.php';
    $result = mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    $row = mysqli_fetch_assoc($result);
    $count = $row['count'];
    mysqli_close($connection);

    if ($count > 0) {
      return DATA_FOUND;
    }

    return NO_DATA_FOUND;
  }
];

function getCityId($city) {
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

function deletePhone($id) {
  $query = "DELETE FROM phone WHERE id=$id;";
  include '#/connection.php';
  mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
  mysqli_close($connection);
}

function updateMember($no, $member) {
  global $API;

  $set = "";
  foreach ($member as $key => $value) {
    if ($key == 'is_parent') {
      if ($value) {
        $set .= "$key=1, ";
      } else {
        $set .= "$key=0, ";
      }
    } else if ($key == 'city') {
      $city = getCityId($member['city']);
      $set .= "city=$city, ";
    } else if ($key != 'phone') {
      $set .= "$key='$value', ";
    }
  }

  $set = rtrim($set, ", ");

  if (empty($set)) {
    return OPERATION_SUCCESSFUL;
  }

  $query = "UPDATE member SET $set WHERE no=$no;";

  include '#/connection.php';
  mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
  mysqli_close($connection);

  return OPERATION_SUCCESSFUL;
}

function updatePhone($id, $number, $note) {
  $query = "UPDATE phone SET number='$number',
                             note='$note'
            WHERE id='$id';";

  include '#/connection.php';
  mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
  mysqli_close($connection);
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
            INNER JOIN city ON member.city = city.id
            INNER JOIN state ON city.state = state.code
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
?>
