<?php
// Public API functions
$deleteMember = function($params) {
  $no = $params['no'];
  $query = "DELETE FROM error WHERE member=$no;
            DELETE FROM item_feed WHERE member=$no;
            DELETE FROM comment WHERE member=$no;
            DELETE FROM phone WHERE member=$no;
            DELETE FROM reservation WHERE member=$no;
            DELETE FROM transaction WHERE member=$no AND type=(SELECT id FROM transaction_type WHERE code='RESERVE');
            DELETE FROM member WHERE no=$no;";

  include '#/connection.php';

  if (!mysqli_multi_query($connection, $query)) {
    http_response_code(500);
  }

  mysqli_close($connection);    
};

$getMember = function($params) {  
  $no = $params['no'];
  $member = selectMember($no);
  $member['phone'] = selectPhone($no);
  $member['account']['comment'] = getMemberComment($no);

  if ($member['firstName'] == 'BLU') {
    $member['account']['copies'] = array_merge(selectCopiesForMember($no), selectDonations());
  } else {
    $member['account']['copies'] = selectCopiesForMember($no);
    $member['account']['transfers'] = getTranferDates($no);
  }

  if ($member['isParent']) {
    $member['account']['reservation'] = selectReservationsForMember($no);
  }

  return $member; 
};

$memberRenew = function($params) {
  remewMember($params['no']);
};

$memberSearch = function($data = []) {
  if (!isset($data['search'])) {
    http_response_code(400);
    return [ 'message' => 'Missing parameter \'search\'' ];
  }

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
};

$memberInsert = function($data = []) {
  $required = ['firstName', 'lastName', 'email', 'isParent', 'address', 'zip', 'phone'];
  foreach($required as $field) {
    if (!isset($data[$field])) {
      http_response_code(400);
      return [ "message" => "Missing parameter '$field'" ];
    }
  }

  $no = insertMember($data);
  
  foreach($data['phone'] AS $phone) {
    insertPhone($no, $phone['number'], $phone['note']);
  }
  
  return [ 'no' => $no ];
};

$memberUpdate = function($params, $data = []) {
  $required = ['no', 'firstName', 'lastName', 'email', 'isParent', 'address', 'zip', 'phone'];
  foreach($required as $field) {
    if (!isset($data[$field])) {
      http_response_code(400);
      return [ "message" => "Missing parameter '$field'" ];
    }
  }

  deletePhones($params['no']);
  $no = updateMember($params['no'], $data);

  foreach($data['phone'] as $phone) {
    insertPhone($no, $phone['number'], $phone['note']);
  }

  return [ 'no' => $no ];
};

$memberCommentInsert = function($params, $data = []) {
  if (!isset($data['comment'])) {
    http_response_code(400);
    return [ 'message' => 'Missing parameter \'comment\'' ];
  }

  $no = $params['no'];
  $comment = $data['comment'];
  $employee = isset($data['employee']) ? $data['employee'] : 1;
  $query = "INSERT INTO comment(comment, member, updated_at, updated_by)
            VALUES (?,?,CURRENT_TIMESTAMP,?);";
  
  include '#/connection.php';
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'sii', $comment, $no, $employee);

  mysqli_stmt_execute($statement);
  $id = mysqli_stmt_insert_id($statement);
  
  mysqli_stmt_close($statement);
  mysqli_close($connection);

  return [ 'id' => $id ];
};

$memberCommentUpdate = function($params, $data = []) {
  if (!isset($data['comment'])) {
    http_response_code(400);
    return [ 'message' => 'Missing parameter \'comment\'' ];
  }

  $id = $params['id'];
  $comment = $data['comment'];
  $employee = isset($data['employee']) ? $data['employee'] : 1;
  $query = "UPDATE comment
            SET comment=?,
                updated_at=CURRENT_TIMESTAMP,
                updated_by=?
            WHERE id=?;";

  include '#/connection.php';
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'sii', $comment, $employee, $id);
  mysqli_stmt_execute($statement);
  
  mysqli_stmt_close($statement);
  mysqli_close($connection);
};

$memberCommentDelete = function($params) {
  $id = $params['id'];
  $query = "DELETE FROM comment WHERE id=?;";

  include '#/connection.php';
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'i', $id);
  mysqli_stmt_execute($statement);
  
  mysqli_stmt_close($statement);
  mysqli_close($connection);
};

$memberPay = function($params) {
  $no = $params['no'];
  $query = "SELECT copy FROM transaction
            WHERE member=?
            AND (type=(SELECT id FROM transaction_type WHERE code='SELL')
                OR type=(SELECT id FROM transaction_type WHERE code='SELL_PARENT'))
            AND copy NOT IN(SELECT copy FROM transaction
                          WHERE member=?
                          AND type=(SELECT id FROM transaction_type WHERE code='PAY'));";
  
  include '#/connection.php';
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'ii', $no, $no);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $copy);

  $copies = [];
  while(mysqli_stmt_fetch($statement)) {
    array_push($copies, $copy);
  }
  
  mysqli_stmt_close($statement);
  mysqli_close($connection);

  batchInsertTransactions($no, $copies, 'PAY');
  renewMember($no);
};

$memberName = function($params) {
  $no = $params['no'];
  $query = "SELECT first_name, last_name FROM member WHERE no=?";

  include '#/connection.php';
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'i', $no);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $firstName, $lastName);
  mysqli_stmt_fetch($statement);

  mysqli_stmt_close($statement);
  mysqli_close($connection);

  return [
    'firstName' => $firstName,
    'lastName' => $lastName,
  ];
};

$memberMerge = function ($data = []) {
  $required = ['no', 'duplicate'];
  foreach($required as $field) {
    if (!isset($data[$field])) {
      http_response_code(400);
      return [ "message" => "Missing parameter '$field'" ];
    }
  }

  $no = $data['no'];
  $duplicate = $data['duplicate'];
  $dates = getDates($no, $duplicate);
  $registration = $dates['registration'];
  $lastActivity = $dates['lastActivity'];
  $query = "UPDATE member SET registration='$registration', last_activity='$lastActivity' WHERE no=$no;
            UPDATE transaction SET member=$no WHERE member=$duplicate;
            UPDATE reservation SET member=$no WHERE member=$duplicate;
            UPDATE comment SET member=$no WHERE member=$duplicate;
            UPDATE error SET member=$no WHERE member=$duplicate;
            DELETE FROM item_feed WHERE member=$duplicate;
            DELETE FROM phone WHERE member=$duplicate;
            DELETE FROM member WHERE no=$duplicate;";

  include '#/connection.php';

  if (mysqli_multi_query($connection, $query)) {
    http_response_code(500);
  }

  mysqli_close($connection);
};

$memberDuplicates = function() {
  $duplicates = [];
  $query = "SELECT
                @no := no,
                CONCAT(first_name, ' ', last_name),
                email,
                registration,
                last_activity
            FROM member
            WHERE no < 180000000
            OR no LIKE CONCAT('%', @no, '%')
            OR email IN (SELECT email FROM member GROUP BY email HAVING COUNT(no) > 1);";

  include '#/connection.php';
  $statement = mysqli_prepare($connection, $query);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $no, $name, $email, $registration, $lastActivity);
  
  while(mysqli_stmt_fetch($statement)) {
    array_push($duplicates, [
      'no' => $no,
      'name' => $name,
      'email' => $email,
      'registration' => $registration,
      'lastActivity' => $lastActivity
    ]);
  }

  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return $duplicates;
};

$memberExists = function($data = []) {
  if (!isset($data['no']) && !isset($data['email'])) {
    http_response_code(400);
    return [ "message" => "You must provide at least one of the following parameters: 'no', 'email'" ];
  }

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
};

$memberInsertCopy = function ($params, $data = []) {
  $required = ['item', 'price'];
  foreach($required as $field) {
    if (!isset($data[$field])) {
      http_response_code(400);
      return [ "message" => "Missing parameter '$field'" ];
    }
  }
  
  $id = insertCopy($data['item'], $data['price']);
  insertTransaction($params['no'], $id, 'ADD');
  renewMember($params['no']);
  $reservation = handleReservation($id, $data['item']);

  return [
    'id' => $id,
    'reservation' => $reservation,
  ];
};

$memberUpdateCopy = function ($params, $data = []) {
  if (!isset($data['price'])) {
    http_response_code(400);
    return [ 'message' => 'Missing parameter \'price\'' ];
  }

  $id = $params['id'];
  $price = $data['price'];
  $query = "UPDATE copy SET price=? WHERE id=?;";

  include '#/connection.php';
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'ii', $price, $id);

  mysqli_stmt_execute($statement);

  mysqli_stmt_close($statement);
  mysqli_close($connection);
};

$memberDeleteCopy = function ($params) {
  $id = $params['id'];
  $query = "DELETE FROM transaction WHERE copy=$id;
            DELETE FROM copy WHERE id=$id;";

  include '#/connection.php';
  $result = mysqli_multi_query($connection, $query);
  mysqli_close($connection);

  if (!$result) {
    http_response_code(500);
    return [ 'message' => 'Query failed' ];
  }
};


// Private ressource functions
function getMemberComment($no) {
  $comments = [];
  $query = "SELECT id, comment, updated_at, updated_by
            FROM comment WHERE member=?";

  include '#/connection.php';
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'i', $no);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $id, $comment, $updatedAt, $updatedBy);
  
  while(mysqli_stmt_fetch($statement)) {
    array_push($comments, [
      'id' => $id,
      'comment' => $comment,
      'updatedAt' => $updatedAt,
      'updatedBy' => $updatedBy
    ]);
  }

  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return $comments;
}

function getCityId($city) {
  if ($city['id'] !== 0) {
    return $city['id'];
  }

  $name = $city['name'];
  $state = $city['state']['code'];
  $query = "SELECT id FROM city WHERE name=?;";

  include '#/connection.php';
  $statement1 = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement1, 's', $name);

  mysqli_stmt_execute($statement1);
  mysqli_stmt_bind_result($statement1, $id);
  mysqli_stmt_fetch($statement1);  
  mysqli_stmt_close($statement1);

  if ($id) {
    mysqli_close($connection);
    return $id;
  }

  $query = "INSERT INTO city(name, state) VALUES (?,?);";
  $statement2 = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement2, 'ss', $name, $state);
  mysqli_stmt_execute($statement2);

  $id = mysqli_stmt_insert_id($statement2);

  mysqli_stmt_close($statement2);  
  mysqli_close($connection);
  return $id;
}

function getDates($no, $duplicate) {
  $data = [];
  $query = "SELECT registration, last_activity FROM member WHERE no IN (?, ?);";

  include "#/connection.php";
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'ii', $no, $duplicate);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $registration, $lastActivity);
  
  while (mysqli_stmt_fetch($statement)) {
    if (!isset($data['registration']) || strtotime($data['registration']) > strtotime($registration)) {
      $data['registration'] = $registration;
    }

    if (!isset($data['lastActivity']) || strtotime($data['lastActivity']) < strtotime($lastActivity)) {
      $data['lastActivity'] = $lastActivity;
    }
  }

  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return $data;
}

function insertPhone($member, $number, $note) {
  $query = "INSERT INTO phone(member, number, note) VALUES (?,?,?);";

  include '#/connection.php';
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'iss', $member, $number, $note);
  mysqli_stmt_execute($statement);
    
  mysqli_stmt_close($statement);
  mysqli_close($connection);
}

function getTranferDates($no) {
  $transfers = [];
  $query = "SELECT DISTINCT(DATE(date))
            FROM transaction
            WHERE member=?
            AND type=(SELECT id FROM transaction_type WHERE code='DONATE')
            ORDER BY date ASC;";

  include '#/connection.php';
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'i', $no);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $date);

  while (mysqli_stmt_fetch($statement)) {
    array_push($transfers, $date);
  }

  mysqli_stmt_close($statement);
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

  $no = $member['no'];
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
            LEFT JOIN city ON member.city = city.id
            LEFT JOIN state ON city.state = state.code
            WHERE no=?";

  include "#/connection.php";
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'i', $no);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $firstName, $lastName, $email, $address,
                          $zip, $isParent, $registration, $lastActivity,
                          $cityId, $cityName, $stateCode, $stateName);
  mysqli_stmt_fetch($statement);
  mysqli_stmt_close($statement);
  mysqli_close($connection);

  return [
    'no' => $no,
    'firstName' => $firstName,
    'lastName' => $lastName,
    'email' => $email,
    'isParent' => $isParent == 1,
    'address' => $address,
    'zip' => $zip,
    'city' => [
      'id' => $cityId,
      'name' => $cityName,
      'state' => [
        'code' => $stateCode,
        'name' => $stateName
      ]
    ],
    'account' => [
      'registration' => $registration,
      'lastActivity' => $lastActivity,
    ]
  ];
}

function selectPhone($no) {
  $phones = [];
  $query = "SELECT id, number, note FROM phone WHERE member=?";

  include '#/connection.php';  
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'i', $no);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $id, $number, $note);

  while(mysqli_stmt_fetch($statement)) {
    array_push($phones, [
      'id' => $id,
      'number' => $number,
      'note' => $note
    ]);
  }

  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return $phones;
}

function fakeMemberNo() {
  $query = "SELECT no FROM member WHERE no LIKE '18%' ORDER BY no DESC LIMIT 1";

  include '#/connection.php';  
  $statement = mysqli_prepare($connection, $query);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $no);
  mysqli_stmt_fetch($statement);

  mysqli_stmt_close($statement);
  mysqli_close($connection);

  return $no ? $no + 1 : 180000000;
}

function insertCopy($itemId, $price) {
  $query = "INSERT INTO copy(item, price) VALUES (?,?);";

  include '#/connection.php';  
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, $itemId, $price);
  mysqli_stmt_execute($statement);

  $id = mysqli_stmt_insert_id($connection);
  
  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return $id;
}

function handleReservation($copy, $item) {
  $data = null;
  include '#/connection.php';

  $query = "SELECT r.id,
                   r.member,
                   m.first_name,
                   m.last_name
            FROM reservation r
            INNER JOIN member m
              ON r.member=m.no WHERE item=?
            ORDER BY date ASC
            LIMIT 1;";

  include '#/connection.php';  
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, $item);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $id, $memberNo, $memberFirstName, $memberLastName);
  mysqli_stmt_close($statement);  

  if ($id) {
    $query = "DELETE FROM reservation WHERE id=$id;
              INSERT INTO transaction(member, copy, date, type)
              VALUES($memberNo,
                     $copy,
                     CURRENT_TIMESTAMP,
                     (SELECT id FROM transaction_type WHERE code='RESERVE')
              );";

    if (!mysqli_multi_query($connection, $query)) {
      mysqli_close($connection);      
      http_response_code(500);
      return;
    }

    $data = [
      'no' => $memberNo,
      'firstName' => $memberFirstName,
      'lastName' => $memberLastName,
    ];
  }

  mysqli_close($connection);
  return $data;
}

function renewMember($no) {
  $query = "UPDATE member SET last_activity=CURRENT_TIMESTAMP WHERE no=?;";

  include '#/connection.php';
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'i', $no);  
  mysqli_stmt_execute($statement);

  mysqli_stmt_close($statement);
  mysqli_close($connection);
}

function selectDonations() {
  $copies = [];
  $query = "SELECT copy.id,
                   copy.item,
                   copy.price,
                   item.name,
                   item.is_book,
                   item.edition,
                   item.editor,
                   status_history.date,
                   status.code
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
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_result($statement, $id, $item, $price, $name, $isBook,
                     $edition, $editor, $statusDate, $statusCode);  
  mysqli_stmt_execute($statement);

  while (mysqli_stmt_fetch($statement)) {
    $transaction = selectTransactions($id);
    
    if ($transaction) {
      array_push($copies, [
        'id' => $id,
        'price' => $price,
        'transaction' => $transaction,
        'item' => [
          'id' => $item,
          'name' => $name,
          'edition' => $edition,
          'editor' => $editor,
          'isBook' => $isBook == 1,
          'status' => $isBook == 1 ? [ $statusCode => $statusDate ] : []
        ]
      ]);
    }
  }

  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return $copies;
}

function selectCopiesForMember($no) {
  $copies = [];
  $query = "SELECT copy.id,
                   copy.item,
                   copy.price,
                   item.name,
                   item.is_book,
                   item.edition,
                   item.editor,
                   status_history.date,
                   status.code
            FROM copy
            INNER JOIN item
              ON copy.item = item.id
            INNER JOIN status_history
              ON item.id=status_history.item AND item.status=status_history.status
            INNER JOIN status
              ON item.status=status.id
            WHERE copy.id IN (SELECT DISTINCT copy
                              FROM transaction
                              WHERE member=?)
            AND copy.id NOT IN (SELECT copy
                                FROM transaction
                                WHERE member=?
                                AND type=(SELECT id
                                          FROM transaction_type
                                          WHERE code='DONATE'))";

  include '#/connection.php';
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'ii', $no, $no);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $id, $itemId, $price, $name, $isBook, $edition, $editor, $statusDate, $statusCode);

  while (mysqli_stmt_fetch($statement)) {
    $transaction = selectTransactions($id);
    
    if ($transaction) {
      array_push($copies, [
        'id' => $id,
        'price' => $price,
        'transaction' => $transaction,
        'item' => [
          'id' => $itemId,
          'name' => $name,
          'edition' => $edition,
          'editor' => $editor,
          'isBook' => $isBook == 1,
          'status' => $isBook == 1 ? [ $statusCode => $statusDate ] : [],
          'author' => $isBook == 1 ? selectAuthor($itemId) : []
        ]
      ]);
    }
  }

  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return $copies;
}
?>
