<?php
// Public API functions
$itemExists = function($params) {
  $ean13 = $params['ean13'];
  $query = "SELECT id FROM item WHERE ean13=?";

  $connection = getConnection();
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 's', $ean13);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $id);
  mysqli_stmt_fetch($statement);

  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return [ 'id' => $id ];
};

$itemList = function($data = []) {
  return isset($data['search']) ? searchItems($data) : listItems();
};

$getItem = function($params) {
  global $accessLevel;
  $isPublic = $accessLevel == 'public' || $accessLevel == 'member';

  $id = $params['id'];
  $query = "SELECT item.name,
                   item.publication,
                   item.edition,
                   item.editor,
                   item.ean13,
                   item.is_book,
                   item.comment,
                   subject.id,
                   subject.name,
                   category.id,
                   category.name
            FROM item
            INNER JOIN subject ON item.subject=subject.id
            INNER JOIN category ON subject.category=category.id
            WHERE item.id=?";

  $connection = getConnection();
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'i', $id);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $name, $publication, $edition, $editor,
                          $ean13, $isBook, $comment, $subjectId, $subjectName,
                          $categoryId, $categoryName);
  mysqli_stmt_fetch($statement);

  mysqli_stmt_close($statement);
  mysqli_close($connection);

  $copies = selectCopiesForItem($id);

  if ($isPublic) {
    $quantity = 0;
    $avgPrice = 0;

    foreach($copies as $copy) {
      if (count($copy['transaction']) == 1) {
        $avgPrice += $copy['price'];
        $quantity++;
      }
    }

    if ($quantity != 0) {
      $avgPrice /= $quantity;
    }

    $copies = [
      'quanity' => $quantity,
      'averagePrice' => $avgPrice,
    ];
    $reservations = null;
    $storage = null;
  } else {
    $storage = selectStorage($id);
    $reservations = selectReservationsForItem($id);
  }

  return [
    'id' => $id,
    'name' => $name,
    'publication' => $publication,
    'edition' => $edition,
    'editor' => $editor,
    'subject' => [
      'id' => $subjectId,
      'name' => $subjectName,
      'category' => [
        'id' => $categoryId,
        'name' => $categoryName
      ]
    ],
    'isBook' => $isBook == 1,
    'ean13' => $ean13,
    'comment' => $comment,
    'author' => $isBook == 1 ? selectAuthor($id) : [],
    'copies' => $copies,
    'status' => selectStatus($id),
    'storage' => $storage,
    'reservation' => $reservations
  ];
};

$getItemName = function($params) {
  $ean13 = $params['ean13'];
  $query = "SELECT id, name FROM item WHERE ean13=?;";

  $connection = getConnection();
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 's', $ean13);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $id, $name);
  mysqli_stmt_fetch($statement);

  mysqli_stmt_close($statement);
  mysqli_close($connection);

  return [
    'id' => $id,
    'name' => $name
  ];
};

$itemUpdateStatus = function($params, $data = []) {
  if (!isset($data['status'])) {
    http_response_code(400);
    return [ 'message' => 'Missing parameter \'status\'' ];
  }

  updateItemStatus($params['id'], $data['status']);
};

$itemUpdateStorage = function($params, $data = []) {
  if (!isset($data['storage'])) {
    http_response_code(400);
    return [ 'message' => 'Missing parameter \'storage\'' ];
  }

  $id = $params['id'];
  $storage = $data['storage'];

  $query = "DELETE FROM storage WHERE item=$id;";

  foreach ($storage as $no) {
    $query .= "INSERT INTO storage(no, item) VALUES($no, $id);";
  }

  $connection = getConnection();
  if (!mysqli_multi_query($connection, $query)) {
    http_response_code(500);
  }

  mysqli_close($connection);
};

$itemDelete = function($params) {
  $id = $params['id'];
  $query = "DELETE FROM status_history WHERE item=$id;
            DELETE FROM item_author WHERE item=$id;
            DELETE FROM error WHERE item=$id;
            DELETE FROM item_feed WHERE item=$id;
            DELETE FROM reservation WHERE item=$id;
            DELETE FROM storage WHERE item=$id;
            DELETE FROM item WHERE id=$id;";

  $connection = getConnection();
  if (!mysqli_multi_query($connection, $query)) {
    http_response_code(500);
  }

  mysqli_close($connection);
};

$itemInsert = function($data = []) {
  $required = ['name', 'subject', 'isBook', 'comment'];
  foreach($required as $field) {
    if (!isset($data[$field])) {
      http_response_code(400);
      return [ "message" => "Missing parameter '$field'" ];
    }
  }

  if ($data['isBook'] && !isset($data['author'])) {
    http_response_code(400);
    return [ "message" => "Missing parameter 'author'" ];
  }

  $name = $data['name'];
  $subject = $data['subject']['id'];
  $publication = isset($data['publication']) ? $data['publication'] : null;
  $edition = isset($data['edition']) ? $data['edition'] : null;
  $editor = isset($data['editor']) ? $data['editor'] : null;
  $ean13 = isset($data['ean13']) ? $data['ean13'] : null;
  $isBook = $data['isBook'] ? 1 : 0;
  $comment = $data['comment'];
  $query = "INSERT INTO item(name, subject, publication, edition, editor, ean13, is_book, comment, status) 
            VALUES (?,?,?,?,?,?,?,?,1);";

  $connection = getConnection();
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'siiissis', $name, $subject, $publication, $edition, $editor, $ean13, $isBook, $comment);

  mysqli_stmt_execute($statement);
  $id = mysqli_stmt_insert_id($statement);

  mysqli_stmt_close($statement);
  mysqli_close($connection);

  updateItemStatus($id, 'VALID');

  if ($data['isBook']) {
    updateAuthors($id, $data['author']);
  }

  return [ 'id' => $id ];
};

$itemUpdate = function($params, $data = []) {
  $required = ['name', 'subject', 'isBook', 'comment'];
  foreach($required as $field) {
    if (!isset($data[$field])) {
      http_response_code(400);
      return [ "message" => "Missing parameter '$field'" ];
    }
  }

  if ($data['isBook'] && !isset($data['author'])) {
    http_response_code(400);
    return [ "message" => "Missing parameter 'author'" ];
  }

  $id = $params['id'];
  $name = $data['name'];
  $subject = $data['subject']['id'];
  $publication = isset($data['publication']) ? $data['publication'] : null;
  $edition = isset($data['edition']) ? $data['edition'] : null;
  $editor = isset($data['editor']) ? $data['editor'] : null;
  $ean13 = isset($data['ean13']) ? $data['ean13'] : null;
  $isBook = $data['isBook'] ? 1 : 0;
  $comment = $data['comment'];
  $query = "UPDATE item
            SET name=?, subject=?, publication=?, edition=?, editor=?, ean13=?, is_book=?, comment=?
            WHERE id=?;";

  $connection = getConnection();
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'siiissisi', $name, $subject, $publication, $edition, $editor, $ean13, $isBook, $comment, $id);

  mysqli_stmt_execute($statement);

  mysqli_stmt_close($statement);
  mysqli_close($connection);

  if ($data['isBook']) {
    updateAuthors($id, $data['author']);
  }
};

$itemMerge = function($params) {
  $id = $params['id'];
  $duplicate = $params['duplicate'];
  $query = "UPDATE copy SET item=$id WHERE item=$duplicate;
            UPDATE reservation SET item=$id WHERE item=$duplicate;
            UPDATE item_feed SET item=$id WHERE item=$duplicate;
            UPDATE error SET item=$id WHERE item=$duplicate;
            DELETE FROM item_author WHERE item=$duplicate;
            DELETE FROM status_history WHERE item=$duplicate;
            DELETE FROM storage WHERE item=$duplicate;
            DELETE FROM item WHERE id=$duplicate;";

  $connection = getConnection();
  if (!mysqli_multi_query($connection, $query)) {
    http_response_code(500);
  }

  mysqli_close($connection);
};

// Private ressources functions
function updateAuthors($itemId, $authors) {
  $connection = getConnection();
  $authorList = [];

  $query = "DELETE FROM item_author WHERE item=?";
  $deleteAuthorLinksStmt = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($deleteAuthorLinksStmt, 'i', $itemId);      
  mysqli_stmt_execute($deleteAuthorLinksStmt);
  mysqli_stmt_close($deleteAuthorLinksStmt);

  foreach ($authors as $author) {
    $firstName = $author['firstName'];
    $lastName = $author['lastName'];

    if (isset($author['id']) && $author['id'] != 0) {
      $authorId = $author['id'];

      $query = "UPDATE author SET first_name=?, last_name=? WHERE id=?;";
      $updateAuthorStmt = mysqli_prepare($connection, $query);
      mysqli_stmt_bind_param($updateAuthorStmt, 'ssi', $firstName, $lastName, $authorId);      
      mysqli_stmt_execute($updateAuthorStmt);
      mysqli_stmt_close($updateAuthorStmt);      
    } else {
      $query = "INSERT INTO author(first_name, last_name) VALUES(?,?)";
      $insertAuthorStmt = mysqli_prepare($connection, $query);
      mysqli_stmt_bind_param($insertAuthorStmt, 'ss', $firstName, $lastName);      
      mysqli_stmt_execute($insertAuthorStmt);
      $authorId = mysqli_stmt_insert_id($insertAuthorStmt);
      mysqli_stmt_close($insertAuthorStmt);
    }

    $query = "INSERT INTO item_author(item, author) VALUES(?,?);";
    $insertAuthorLinkStmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($insertAuthorLinkStmt, 'ii', $itemId, $authorId);      
    mysqli_stmt_execute($insertAuthorLinkStmt);
    mysqli_stmt_close($insertAuthorLinkStmt);

    array_push($authorList, [
      'id' => $authorId,
      'firstName' => $firstName,
      'lastName' => $lastName
    ]);
  }

  mysqli_close($connection);  
  return $authorList;
}

function selectAuthor($itemId) {
  $authors = [];  
  $query = "SELECT id, first_name, last_name
            FROM author
            INNER JOIN item_author
              ON author.id = item_author.author
            WHERE item_author.item = ?";

  $connection = getConnection();
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'i', $itemId);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $id, $firstName, $lastName);

  while (mysqli_stmt_fetch($statement)) {    
    array_push($authors, [
      'id' => $id,
      'firstName' => $firstName,
      'lastName' => $lastName
    ]);     
  }
  
  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return $authors;
}

function searchItems($data) {
  $search = '%' . str_replace(' ', '%', $data['search']) . '%';
  $outdated = isset($data['outdated']) && $data['outdated'] == 'true';

  $items = [];
  $query = "SELECT item.id, name, edition, publication, editor, is_book
            FROM item
            LEFT JOIN item_author
              ON item.id = item_author.item
            LEFT JOIN author
              ON item_author.author = author.id
            WHERE (
              name LIKE ?
              OR editor LIKE ?
              OR CONCAT(first_name, ' ', last_name) LIKE ?
              OR CONCAT(last_name, ' ', first_name) LIKE ?
            )";

  if (!$outdated) {
    $query .= " AND status = 1";
  }

  $query .= " GROUP BY item.id;";

  $connection = getConnection();
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'ssss', $search, $search, $search, $search);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $id, $name, $publication, $edition, $editor, $isBook);

  $authors = [];
  while (mysqli_stmt_fetch($statement)) {
    $item = [
      'id' => $id,
      'name' => $name,
      'publication' => $publication,
      'edition' => $edition,
      'editor' => $editor,
      'isBook' => $isBook === 1 ? true : false,
      'author' => selectAuthor($id)
    ];

    array_push($items, $item);     
  }
  
  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return $items;
}

function listItems() {
  $query = "SELECT
              item.id,
              item.name,
              item.publication,
              item.edition,
              item.editor,
              item.is_book,
              status.code,
              status_history.date,
              subject.id,
              subject.name
            FROM item
            INNER JOIN status_history
              ON item.status = status_history.status
              AND item.id = status_history.item
            INNER JOIN status
              ON item.status = status.id
            INNER JOIN subject
              ON item.subject = subject.id
            ORDER BY item.name";

  $connection = getConnection();
  $statement = mysqli_prepare($connection, $query);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $id, $name, $publication, $edition, $editor, $isBook, $status, $statusDate, $subjectId, $subjectName);

  $items = [];
  while (mysqli_stmt_fetch($statement)) {    
    array_push($items, [
      'id' => $id,
      'name' => $name,
      'publication' => $publication,
      'edition' => $edition,
      'editor' => $editor,
      'isBook' => $isBook == 1,
      'subject' => [
        'id' => $subjectId,
        'name' => $subjectName
      ],
      'status' => [
        $status => $statusDate
      ],
      'stats' => [
        'inStock' => getQuantityInStock($id)
      ],
      'author' => $isBook == 1 ? selectAuthor($id) : []
    ]);     
  }
  
  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return $items;
}

function selectStorage($id) {
  $storage = [];
  $query = "SELECT no FROM storage WHERE item=?;";

  $connection = getConnection();
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'i', $id);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $no);

  while (mysqli_stmt_fetch($statement)) {
    array_push($storage, $no);
  }

  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return $storage;
}

function selectStatus($id) {
  $status = [];
  $query = "SELECT status_history.date, status.code
            FROM status_history
            INNER JOIN status
              ON status_history.status=status.id
            WHERE status_history.item=?;";

  $connection = getConnection();
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'i', $id);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $date, $code);

  while (mysqli_stmt_fetch($statement)) {
    $status[$code] = $date;
  }

  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return $status;
}

function updateItemStatus($id, $status) {
  $query = "UPDATE item SET status=(SELECT id FROM status WHERE code='$status') WHERE id=$id;
  INSERT INTO status_history(item, status, date)
  VALUES($id, (SELECT id FROM status WHERE code='$status'), CURRENT_TIMESTAMP);";

  if ($status == "VALID") {
    $query .= "DELETE FROM status_history
      WHERE item=$id
      AND status IN (SELECT id FROM status WHERE code IN ('OUTDATED', 'REMOVED'));";
  } else if ($status == "OUTDATED") {
    $query .= "DELETE FROM status_history
      WHERE item=$id
      AND status=(SELECT id FROM status WHERE code='REMOVED');";
  }

  $connection = getConnection();
  if (!mysqli_multi_query($connection, $query)) {
    http_response_code(500);
  }

  mysqli_close($connection);
}

function getQuantityInStock($id) {
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

  $connection = getConnection();
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'ii', $id, $id);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $inStock);
  mysqli_stmt_fetch($statement);

  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return $inStock;
}

function selectCopiesForItem($itemId) {
  $copies = [];
  $query = "SELECT copy.id,
                   copy.price,
                   member.no,
                   member.first_name,
                   member.last_name,
                   member.last_activity
            FROM copy
            INNER JOIN transaction
              ON copy.id = transaction.copy
            INNER JOIN member
              ON transaction.member = member.no
            WHERE copy.item=?
            AND transaction.type = (SELECT id
                                    FROM transaction_type
                                    WHERE code = 'ADD');";

  $connection = getConnection();
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'i', $itemId);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $id, $price, $memberNo, $memberFirstName, $memberLastName, $memberLastActivity);

  while (mysqli_stmt_fetch($statement)) {
    $transaction = selectTransactions($id);

    if ($transaction) {
      array_push($copies, [
        'id' => $id,
        'price' => $price,
        'member' => [
          'no' => $memberNo,
          'firstName' => $memberFirstName,
          'lastName' => $memberLastName,
          'account'=> [
            'lastActivity' => $memberLastActivity
          ]
        ],
        'transaction' => $transaction
      ]);
    }
  }

  mysqli_stmt_close($statement);    
  mysqli_close($connection);
  return $copies;
}
?>
