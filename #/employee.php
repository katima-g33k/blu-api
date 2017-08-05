<?php
$API['employee'] = [
  'delete' => function($data) {
    $query = "DELETE FROM employee WHERE id = ?;";

    include "#/connection.php";
    $statement = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($statement,'i', $id);

    $id = $data['id'];

    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    mysqli_close($connection);

    return OPERATION_SUCCESSFUL;
  },
  'insert' => function($data) {
    $query = "INSERT INTO employee(username, password, admin, active) VALUES (?, ?, ?, ?);";

    include "#/connection.php";
    $statement = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($statement,'ssii', $username, $password, $isAdmin, $isActive);

    $username = strip_tags($data['username']);
    $password = $data['password'];
    $isAdmin = isset($data['isAdmin']) && $data['isAdmin'] ? 1 : 0;
    $isActive = isset($data['isActive']) && $data['isActive'] ? 1 : 0;

    mysqli_stmt_execute($statement);

    $id = mysqli_insert_id($connection);

    mysqli_stmt_close($statement);
    mysqli_close($connection);

    return $id;
  },
  'list' => function($data) {
    $query = "SELECT id, username, admin, active FROM employee";

    include "#/connection.php";
    $statement = mysqli_prepare($connection, $query);

    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $id, $username, $isAdmin, $isActive);

    $employees = [];
    while (mysqli_stmt_fetch($statement)) {
       $employee = [
        'id' => $id,
        'username' => $username,
        'isAdmin' => $isAdmin == 1,
        'isActive' => $isActive == 1, 
      ];
      array_push($employees, $employee);     
    }
    
    mysqli_stmt_close($statement);
    mysqli_close($connection);
    return $employees;
  },
  'login' => function($data) {
    $query = "SELECT id, username, admin FROM employee WHERE username=? AND password=? AND active=1;";

    include "#/connection.php";
    $statement = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($statement,'ss', $username, $password);

    $username = $data['username'];
    $password = $data['password'];

    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $id, $username, $isAdmin);
    mysqli_stmt_fetch($statement);

    if ($id) {
      $employee = [
        'id' => $id,
        'username' => $username,
        'isAdmin' => $isAdmin == 1,
      ];

      mysqli_stmt_close($statement);
      mysqli_close($connection);
      return $employee;
    }

    mysqli_stmt_close($statement);
    mysqli_close($connection);
    return UNAUTHORIZED;
  },
  'update' => function($data) {
    $id = $data['id'];
    $employee = $data['employee'];

    $username = $employee['username'];
    $password = isset($employee['password']) ? $employee['password'] : false;
    $isAdmin = isset($employee['isAdmin']) && $employee['isAdmin'] ? 1 : 0;
    $isActive = isset($employee['isActive']) && $employee['isActive'] ? 1 : 0;

    if (isset($employee['password'])) {
      $query = "UPDATE employee SET username=?, password=?, admin=?, active=? WHERE id=?;";

      include "#/connection.php";
      $statement = mysqli_prepare($connection, $query);
      mysqli_stmt_bind_param($statement,'ssiii', $username, $password, $isAdmin, $isActive, $id);
    } else {
      $query = "UPDATE employee SET username=?, admin=?, active=? WHERE id=?;";

      include "#/connection.php";
      $statement = mysqli_prepare($connection, $query);
      mysqli_stmt_bind_param($statement,'siii', $username, $isAdmin, $isActive, $id);
    }

    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    mysqli_close($connection);

    return OPERATION_SUCCESSFUL;
  },
];
?>