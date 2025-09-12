<?php
class Database {
  private $conn;
  public function getConnection() {
    if ($this->conn) return $this->conn;
    $host = '127.0.0.1';
    $db   = 'faculty_attendance_system';
    $user = 'root';
    $pass = '';
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $opts = [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ];
    $this->conn = new PDO($dsn, $user, $pass, $opts);
    return $this->conn;
  }
}
