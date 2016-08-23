<?php
require_once 'index.php';

function executeQuery($function, $data) {
  switch ($function) {
    case 'insert':
      return insert($data);
    case 'update':
      return update($data['id'], $data['item']);
    case 'delete':
      return delete($data['id']);
    case 'search':
      return search($data['search']);
    case 'update_comment':
      return updateComment($data['id'], $data['comment']);
    case 'update_status':
      return updateStatus($data['id'], $data['status']);
    case 'update_storage':
      return updateStorage($data['id'], json_encode($data['storage']));
    case 'select':
      return select($data['id']);
    case 'select_code':
      return selectByCode($data['ean13']);
  }
}

function search($search) {
  $items = [];
  $query = "SELECT id, name, publication, editor, is_book
            FROM item
            WHERE";

  $searchValues = explode(" ", $search);

  foreach ($searchValues as $index => $value) {
    if ($index != 0) {
      $query .= " OR";
    }

    $query .= " name LIKE '%$value%'";
  }

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die("Query failed: '$query'");
  mysqli_close($connection);

  while($row = mysqli_fetch_assoc($result)) {
    $isBook = $row['is_book'] != 0;

    $item = [
      'id' => $row['id'],
      'name' => $row['name'],
      'publication' => $row['publication'],
      'editor' => $row['editor'],
      'is_book' => $isBook,
      'author' => selectAuthor($row['id'])
    ];

    array_push($items, $item);
  }

  return $items;
}

function selectAuthor($item) {
  $authors = [];
  $query = "SELECT id, first_name, last_name
            FROM author
            INNER JOIN item_author ON author.id = item_author.author
            WHERE item_author.item = $item";

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die("Query failed: '$query'");

  while($row = mysqli_fetch_assoc($result)) {
    if ($row['first_name'] == null) {
      $row['first_name'] = "";
    }

    if ($row['last_name'] == null) {
      $row['last_name'] = "";
    }

    $author = [
      'id' => $row['id'],
      'first_name' => $row['first_name'],
      'last_name' => $row['last_name']
    ];

    array_push($authors, $author);
  }

  mysqli_close($connection);
  return $authors;
}

function select($id) {
  $query = "SELECT * FROM item WHERE id=$id";

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die("Query failed: '$query'");
  $row = mysqli_fetch_assoc($result);

  mysqli_close($connection);
  return $row;
}

function selectByCode($ean13) {
  // $query = "SELECT * FROM item WHERE `ean13`='$ean13'";
  $query = "SELECT propriete_article.id_article FROM propriete_valeur
          INNER JOIN propriete_article ON propriete_valeur.id=propriete_article.id_propriete_valeur
          WHERE propriete_valeur.valeur='$ean13' AND propriete_valeur.id_propriete=13";

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die("Query failed: '$query'");
  $row = mysqli_fetch_assoc($result);
  $id = $row['id_article'];

  mysqli_close($connection);
  return select($id);
}

function selectCopies($id) {
  $copies = [];
  $query = "SELECT exemplaire.id, exemplaire.prix, membre.no, membre.prenom, membre.nom
            FROM exemplaire
            INNER JOIN transaction ON exemplaire.id=transaction.id_exemplaire
            INNER JOIN membre ON transaction.no_membre=membre.no
            WHERE exemplaire.id_article=$id";

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die("Query failed: '$query'");

  while($row = mysqli_fetch_assoc($result)) {
    $copy = [
      'id' => $row['id'],
      'prix' => $row['prix'],
      'membre' => [
        'no' => $row['no'],
        'prenom' => $row['prenom'],
        'nom' => $row['nom']
      ]
    ];

    array_push($copies, $copy);
  }

  mysqli_close($connection);

  require_once 'res_sql.php';
  foreach ($copies as $copy) {
    $copy['transaction'] = getCopyTransactions($copy['id']);
  }

  return $copies;
}

function updateComment($id, $comment) {
  $query = "SELECT id FROM propriete_valeur WHERE id_propriete=17 AND id IN(SELECT id_propriete_valeur FROM propriete_article WHERE id_article=$id)";

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die("Query failed: '$query'");
  $row = mysqli_fetch_assoc($result);

  if (isset($row['id'])) {
    $propertyId = $row['id'];
    $query = "UPDATE propriete_valeur SET valeur='$comment' WHERE id=$propertyId";
    $result = mysqli_query($connection, $query) or die("Query failed: '$query'");
  } else {
    $query = "INSERT INTO propriete_valeur(id_propriete, valeur) VALUES (17, '$comment');
              INSERT INTO propriete_article(id_propriete_valeur, id_article) VALUES (LAST_INSERT_ID(), $id)";
    $result = mysqli_multi_query($connection, $query) or die("Query failed: '$query'");
  }

  mysqli_close($connection);
  return [ 'code' => 200, 'message' => 'UPDATE_SUCCESSFUL' ];
}

function updateStatus($id, $status) {
  $query = "UPDATE item SET status=(SELECT id FROM status WHERE code='$status') WHERE id=$id;
            INSERT INTO status_history(item, status, date) VALUES($id, (SELECT id FROM status WHERE code='$status'), CURRENT_TIMESTAMP)";

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die("Query failed: '$query'");
  mysqli_close($connection);

  return [ 'code' => 200, 'message' => 'UPDATE_SUCCESSFUL' ];
}

function updateStorage($id, $storage) {
  $query = "SELECT id FROM propriete_valeur WHERE id_propriete=18 AND id IN(SELECT id_propriete_valeur FROM propriete_article WHERE id_article=$id)";

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die("Query failed: '$query'");
  $row = mysqli_fetch_assoc($result);

  if (isset($row['id'])) {
    $propertyId = $row['id'];
    $query = "UPDATE propriete_valeur SET valeur='$storage' WHERE id=$propertyId";
    $result = mysqli_query($connection, $query) or die("Query failed: '$query'");
  } else {
    $query = "INSERT INTO propriete_valeur(id_propriete, valeur) VALUES (18, '$storage');
              INSERT INTO propriete_article(id_propriete_valeur, id_article) VALUES (LAST_INSERT_ID(), $id)";
    $result = mysqli_multi_query($connection, $query) or die("Query failed: '$query'");
  }

  mysqli_close($connection);
  return [ 'code' => 200, 'message' => 'UPDATE_SUCCESSFUL' ];
}


























function selectListeArticle($nom) {
  $query = "SELECT id, nom FROM article WHERE nom LIKE '%$nom%';";
  $json_array = array();

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die (mysqli_error($connection));
  $fields = mysqli_fetch_fields($result);

  while($row = mysqli_fetch_assoc($result)) {
    $row_array = array();

    foreach($fields AS $field) {
      $field_array[$field->name] = $row[$field->name];
      array_push($row_array, $field_array);
      $field_array = null;
    }
    array_push($json_array, proprietesArticle($row['id'], $row_array));
  }

  mysqli_close($connection);
  echo json_encode($json_array);
}

function proprietesArticle($id, $row_array) {
  $json_array = array();

  $q = "SELECT (SELECT valeur FROM propriete_valeur WHERE id_propriete=9 AND id IN (SELECT id_propriete_valeur FROM propriete_article WHERE id_article=$id)) AS editeur, (SELECT valeur FROM propriete_valeur WHERE id_propriete=12 AND id IN (SELECT id_propriete_valeur FROM propriete_article WHERE id_article=$id)) AS edition, (SELECT valeur FROM propriete_valeur WHERE id_propriete=11 AND id IN (SELECT id_propriete_valeur FROM propriete_article WHERE id_article=$id)) AS annee, (SELECT valeur FROM propriete_valeur WHERE id_propriete=2 AND id IN (SELECT id_propriete_valeur FROM propriete_article WHERE id_article=$id)) AS auteur_1";/*, (SELECT valeur FROM propriete_valeur WHERE id_propriete=3 AND id IN (SELECT id_propriete_valeur FROM propriete_article WHERE id_article=$id)) AS auteur_2, (SELECT valeur FROM propriete_valeur WHERE id_propriete=4 AND id IN (SELECT id_propriete_valeur FROM propriete_article WHERE id_article=$id)) AS auteur_3, (SELECT valeur FROM propriete_valeur WHERE id_propriete=4 AND id IN (SELECT id_propriete_valeur FROM propriete_article WHERE id_article=$id)) AS auteur_3, (SELECT valeur FROM propriete_valeur WHERE id_propriete=5 AND id IN (SELECT id_propriete_valeur FROM propriete_article WHERE id_article=$id)) AS auteur_4, (SELECT valeur FROM propriete_valeur WHERE id_propriete=6 AND id IN (SELECT id_propriete_valeur FROM propriete_article WHERE id_article=$id)) AS auteur_5";*/

  include '#/connection.php';
  $r = mysqli_query($connection, $q) or die (mysqli_error($connection));
  $f = mysqli_fetch_fields($r);

  while($row = mysqli_fetch_assoc($r)) {
    foreach($f AS $field) {
      $field_array[$field->name] = $row[$field->name];
      array_push($row_array, $field_array);
      $field_array = null;
    }
  }

  return $row_array;
}

function selectArticle($idArticle) {
  $query = "SELECT propriete.nom AS propriete,
                   propriete_valeur.valeur AS valeur
            FROM propriete_article
            INNER JOIN propriete_valeur
              ON propriete_article.id_propriete_valeur=propriete_valeur.id
            INNER JOIN propriete
              ON propriete_valeur.id_propriete=propriete.id
            WHERE propriete_article.id_article=$idArticle
              AND propriete.nom!='caisse'";

  $json_array = array();

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die (mysqli_error($connection));

  $id_array['id'] = $idArticle;
  array_push($json_array, $id_array);

  while($row = mysqli_fetch_assoc($result)) {
    $row_array = array();

    $row_array[$row['propriete']] = $row['valeur'];
    array_push($json_array, $row_array);
  }

  mysqli_close($connection);
  echo json_encode($json_array);
}

function selectArticleEAN13($ean13) {
  $query = "SELECT id_article
            FROM propriete_article
            INNER JOIN propriete_valeur
              ON propriete_article.id_propriete_valeur=propriete_valeur.id
            WHERE id_propriete=13
              AND valeur='$ean13'";

  include '#/connection.php';
  $result = mysqli_query($connection, $query) or die (mysqli_error($connection));
  $row = mysqli_fetch_assoc($result);

  selectArticle($row['id_article']);
}
?>
