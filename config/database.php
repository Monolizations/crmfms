<?php
// Set timezone to Manila (UTC+8) for consistent time handling across the application
date_default_timezone_set('Asia/Manila');

class Database {
  private $conn;
  public function getConnection() {
    if ($this->conn) return $this->conn;
    $host = '127.0.0.1';
    $db   = 'faculty_attendance_system';
    $user = 'root';
    $pass = '';
    $socket = '/opt/lampp/var/mysql/mysql.sock';
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4;unix_socket=$socket";
    $opts = [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ];
    $this->conn = new PDO($dsn, $user, $pass, $opts);
    return $this->conn;
  }
}
