<?php
$employeeLogin = function($data = []) {
  $username = isset($data['username']) ? $data['username'] : '';
  $password = isset($data['password']) ? $data['password'] : '';
  $query = "SELECT id, username, admin FROM employee WHERE username=? AND password=? AND active=1;";

  $connection = getConnection();
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'ss', $username, $password);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $id, $username, $isAdmin);
  mysqli_stmt_fetch($statement);

  mysqli_stmt_close($statement);
  mysqli_close($connection);

  if ($id) {
    return [
      'id' => $id,
      'username' => $username,
      'isAdmin' => $isAdmin == 1,
    ];
  }

  http_response_code(401);  
  return ['message' => 'Invalid username and password'];
};

$employeeList = function() {
  $employees = [];
  $query = "SELECT id, username, admin, active FROM employee";

  $connection = getConnection();
  $statement = mysqli_prepare($connection, $query);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $id, $username, $isAdmin, $isActive);

  while (mysqli_stmt_fetch($statement)) {
    array_push($employees, [
      'id' => $id,
      'username' => $username,
      'isAdmin' => $isAdmin == 1,
      'isActive' => $isActive == 1, 
    ]);     
  }
  
  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return $employees;
};

$employeeDelete = function($params) {
  $id = $params['id'];
  $query = "DELETE FROM employee WHERE id=?;";
  
  $connection = getConnection();
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement,'i', $id);
  
  mysqli_stmt_execute($statement);
  mysqli_stmt_close($statement);
  mysqli_close($connection);  
};

$employeeInsert = function($data = []) {
  $required = ['username', 'password'];
  foreach($required as $field) {
    if (!isset($data[$field])) {
      http_response_code(400);
      return [ "message" => "Missing parameter '$field'" ];
    }
  }

  $username = $data['username'];
  $password = $data['password'];
  $isAdmin = isset($data['isAdmin']) && $data['isAdmin'] ? 1 : 0;
  $query = "INSERT INTO employee(username, password, admin, active) VALUES (?,?,?,1);";
  
  $connection = getConnection();
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement,'ssi', $username, $password, $isAdmin);
  mysqli_stmt_execute($statement);
  
  $id = mysqli_insert_id($connection);
  
  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return [ 'id' => $id ];
};

$employeeUpdate = function($params, $data = []) {
  if (!isset($data['username'])) {
    http_response_code(400);
    return [ "message" => "Missing parameter 'username'" ];
  }

  $id = $params['id'];  
  $username = $data['username'];
  $password = isset($data['password']) ? $data['password'] : false;
  $isAdmin = isset($data['isAdmin']) && $data['isAdmin'] ? 1 : 0;
  $isActive = isset($data['isActive']) && $data['isActive'] ? 1 : 0;
  
  if (isset($employee['password'])) {
    $query = "UPDATE employee SET username=?, password=?, admin=?, active=? WHERE id=?;";
  
    $connection = getConnection();
    $statement = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($statement,'ssiii', $username, $password, $isAdmin, $isActive, $id);
  } else {
    $query = "UPDATE employee SET username=?, admin=?, active=? WHERE id=?;";
  
    $connection = getConnection();
    $statement = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($statement,'siii', $username, $isAdmin, $isActive, $id);
  }
  
  mysqli_stmt_execute($statement);

  mysqli_stmt_close($statement);
  mysqli_close($connection);
};

function isAdmin($username, $password) {
  $query = "SELECT id from employee WHERE username=? AND password=? AND admin=1 AND active=1;";
  
  $connection = getConnection();
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'ss', $username, $password);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $id);
  mysqli_stmt_fetch($statement);

  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return $id;
}

function isEmployee($username, $password) {
  $query = "SELECT id from employee WHERE username=? AND password=? AND active=1;";
  
  $connection = getConnection();
  $statement = mysqli_prepare($connection, $query);
  mysqli_stmt_bind_param($statement, 'ss', $username, $password);

  mysqli_stmt_execute($statement);
  mysqli_stmt_bind_result($statement, $id);
  mysqli_stmt_fetch($statement);

  mysqli_stmt_close($statement);
  mysqli_close($connection);
  return $id;
}
?>
