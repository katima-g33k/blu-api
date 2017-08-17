<?php
$API['item'] = [
  'delete' => function($data) {
    $id = $data['id'];
    $query = "DELETE FROM status_history WHERE item=$id;
              DELETE FROM item WHERE id=$id;";

    include '#/connection.php';
    mysqli_multi_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    mysqli_close($connection);
    return OPERATION_SUCCESSFUL;
  },

  'exists' => function($data) {
    $ean13 = $data['ean13'];
    $query = "SELECT id FROM item WHERE `ean13`='$ean13';";

    include '#/connection.php';
    $result = mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    $row = mysqli_fetch_assoc($result);
    mysqli_close($connection);

    if (isset($row['id'])) {
      return [ 'code' => 200, 'id' => $row['id'] ]; 
    }

    return NO_DATA_FOUND;
  },

  'insert' => function($data) {
    global $API;
    $item = $data['item'];
    $item['is_book'] = isset($item['is_book']) && $item['is_book'] ? 1 : 0;
    $authors = isset($item['author']) ? $item['author'] : false;
    unset($item['author']);
    $status = 'VALID';

    $query = "INSERT INTO item SET";
    foreach ($item as $key => $value) {
      $query .= " $key='$value',";
    }

    $query .= " status=(SELECT id FROM status WHERE code='$status');";

    include '#/connection.php';
    mysqli_query($connection, $query) or die($query);
    $id = mysqli_insert_id($connection);
    mysqli_close($connection);

    $API['item']['updateStatus']([ 'id' => $id, 'status' => $status ]);
    $authorList = $authors ? updateAuthors($id, $authors) : [];

    return array_merge(OPERATION_SUCCESSFUL, [ 'id' => $id ], [ 'author' => $authorList ]);
  },

  'search' => function($data) {
    $search = '%' . str_replace(' ', '%', $data['search']) . '%';
    $outdated = isset($data['outdated']) && $data['outdated'];

    $items = [];
    $query = "SELECT item.id, name, edition, publication, editor, is_book, first_name, last_name
              FROM item
              INNER JOIN item_author
                ON item.id = item_author.item
              INNER JOIN author
                ON item_author.author = author.id
              WHERE (name LIKE '$search'
              OR editor LIKE '$search'
              OR CONCAT(first_name, ' ', last_name) LIKE '$search'
              OR CONCAT(last_name, ' ', first_name) LIKE '$search')";

    if (!$outdated) {
      $query .= " AND status = (SELECT id FROM status WHERE code ='VALID')";
    }

    $query .= " GROUP BY item.id;";

    include '#/connection.php';
    $result = mysqli_query($connection, $query) or die("Query failed: '$query'");
    mysqli_close($connection);

    while($row = mysqli_fetch_assoc($result)) {
      $isBook = $row['is_book'] != 0;

      $item = [
        'id' => $row['id'],
        'name' => $row['name'],
        'publication' => $row['publication'],
        'edition' => $row['edition'],
        'editor' => $row['editor'],
        'is_book' => $isBook,
        'author' => selectAuthor($row['id'])
      ];

      array_push($items, $item);
    }

    return $items;
  },

  'list' => function() {
    global $API;
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

    include "#/connection.php";
    $statement = mysqli_prepare($connection, $query);

    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $id, $name, $publication, $edition, $editor, $isBook, $status, $statusDate, $subjectId, $subjectName);

    $items = [];
    while (mysqli_stmt_fetch($statement)) {
       $item = [
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
        'status' => [],
        'stats' => [
          'inStock' => $API['copy']['getQuantityInStock']($id)
        ],
        'author' => $isBook == 1 ? selectAuthor($id) : []
      ];

      $item['status'][$status] = $statusDate;

      array_push($items, $item);     
    }
    
    mysqli_stmt_close($statement);
    mysqli_close($connection);
    return $items;
  },

  'select' => function($data) {
    global $API;

    if (isset($data['forCopy']) && isset($data['ean13']) && $data['forCopy']) {
      $ean13 = $data['ean13'];
      $query = "SELECT id, name FROM item WHERE `ean13`=$ean13;";

      include '#/connection.php';
      $result = mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
      $row = mysqli_fetch_assoc($result);
      mysqli_close($connection);
      return [
        'id' => $row['id'],
        'name' => $row['name']
      ];
    }

    $item = selectItem($data);
    if (isset($item['is_book']) && $item['is_book']) {
      $item['author'] = selectAuthor($item['id']);
    }

    $item['copies'] = $API['copy']['selectForItem']($item['id']);
    $item['status'] = $API['item']['selectStatus']($item['id']);
    $item['storage'] = $API['item']['selectStorage']($item['id']);
    $item['reservation'] = $API['reservation']['selectForItem']($item['id']);
    return $item;
  },

  'selectAuthor' => function($id) {
    return selectAuthor($id);
  },

  'selectStorage' => function($id) {
    $storage = [];
    $query = "SELECT no FROM storage WHERE item=$id;";

    include '#/connection.php';
    $result = mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    mysqli_close($connection);

    while ($row = mysqli_fetch_assoc($result)) {
      array_push($storage, $row['no']);
    }

    return $storage;
  },

  'selectStatus' => function($id) {
    $status = [];
    $query = "SELECT status_history.date, status.code
              FROM status_history
              INNER JOIN status
                ON status_history.status=status.id
              WHERE status_history.item=$id;";

    include '#/connection.php';
    $result = mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));

    while($row = mysqli_fetch_assoc($result)) {
      $status[$row['code']] = $row['date'];
    }

    mysqli_close($connection);
    return $status;
  },

  'update' => function($data) {
    $id = $data['id'];
    $item = $data['item'];
    $authors = $item['author'];
    unset($item['author']);

    updateItem($id, $item);
    $authorList = updateAuthors($id, $authors);

    return array_merge(OPERATION_SUCCESSFUL, [ 'author' => $authorList ]);
  },

  'update_comment' => function($data) {
    $id = $data['id'];
    $comment = $data['comment'];
    $query = "UPDATE item SET comment='$comment' WHERE id=$id";

    include '#/connection.php';
    mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    mysqli_close($connection);

    return OPERATION_SUCCESSFUL;
  },

  'updateStatus' => function($data) {
    $id = $data['id'];
    $status = $data['status'];
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

    include '#/connection.php';
    $result = mysqli_multi_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    mysqli_close($connection);

    return OPERATION_SUCCESSFUL;
  },

  'update_storage' => function($data) {
    $id = $data['id'];
    $storage = $data['storage'];

    $query = "DELETE FROM storage WHERE item=$id;";

    foreach ($storage as $no) {
      $query .= "INSERT INTO storage(no, item) VALUES($no, $id);";
    }

    include '#/connection.php';
    mysqli_multi_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
    mysqli_close($connection);

    return OPERATION_SUCCESSFUL;
  }
];

function updateItem($id, $item) {
  $count = 0;
  $query = "UPDATE item SET";

  foreach ($item as $field => $value) {
    $count++;
    $query .= " $field='$value'";

    if ($count < count($item)) {
      $query .= ',';
    }
  }

  $query .= " WHERE id=$id;";

  include '#/connection.php';
  mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
  mysqli_close($connection);
}

function updateAuthors($itemId, $authors) {
  $authorList = [];

  $query = "DELETE FROM item_author WHERE item=$itemId";
  include '#/connection.php';
  mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
  mysqli_close($connection);

  foreach ($authors as $author) {
    $firstName = $author['first_name'];
    $lastName = $author['last_name'];

    if (isset($author['id']) && $author['id'] !== 0) {
      $authorId = $author['id'];

      $query = "UPDATE author SET first_name='$firstName', last_name='$lastName' WHERE id=$authorId;
                INSERT INTO item_author(item, author) VALUES($itemId, $authorId);";
      include '#/connection.php';
      mysqli_multi_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
      mysqli_close($connection);
    } else {
      $query = "INSERT INTO author(first_name, last_name) VALUES('$firstName', '$lastName')";
      include '#/connection.php';
      mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
      $authorId = mysqli_insert_id($connection);

      $query = "INSERT INTO item_author(item, author) VALUES($itemId, $authorId);";
      mysqli_query($connection, $query) or die(json_encode(INTERNAL_SERVER_ERROR));
      mysqli_close($connection);

      array_push($authorList, [
        'id' => $authorId,
        'first_name' => $firstName,
        'last_name' => $lastName
      ]);
    }
  }

  return $authorList;
}

function selectAuthor($itemId) {
  $query = "SELECT id, first_name, last_name
            FROM author
            INNER JOIN item_author
              ON author.id = item_author.author
            WHERE item_author.item = ?";

  include "#/connection.php";
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'i', $itemId);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $id, $firstName, $lastName);

  $authors = [];
  while (mysqli_stmt_fetch($statement)) {
    $author = [
      'id' => $id,
      'firstName' => $firstName,
      'lastName' => $lastName
    ];

    array_push($authors, $author);     
  }
  
  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return $authors;
}

function selectItem($data) {
  $query = "SELECT item.id,
                   item.name,
                   item.publication,
                   item.edition,
                   item.editor,
                   item.ean13,
                   item.is_book,
                   item.comment,
                   subject.id AS subject_id,
                   subject.name AS subject_name,
                   category.id AS category_id,
                   category.name AS category_name
            FROM item
            INNER JOIN subject ON item.subject=subject.id
            INNER JOIN category ON subject.category=category.id";

  if (isset($data['id'])) {
    $query .= " WHERE item.id=" . $data['id'] . ";";
  } else {
    $query .= " WHERE item.ean13=" . $data['ean13'] . ";";
  }

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die("Query failed: '$query'");
  $row = mysqli_fetch_assoc($result);
  mysqli_close($connection);

  $item = [
    'id' => $row['id'],
    'name' => $row['name'],
    'publication' => $row['publication'],
    'edition' => $row['edition'],
    'editor' => $row['editor'],
    'subject' => [
      'id' => $row['subject_id'],
      'name' => $row['subject_name'],
      'category' => [
        'id' => $row['category_id'],
        'name' => $row['category_name']
      ]
    ]
  ];

  if ($row['is_book'] == 1) {
    $item['is_book'] = true;
  }

  if ($row['ean13'] != null) {
    $item['ean13'] = $row['ean13'];
  }

  if ($row['comment'] != null) {
    $item['comment'] = $row['comment'];
  }

  return $item;
}
?>
