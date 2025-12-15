<?php
// config.php
class Database {
    private $host = "localhost";
    private $db_name = "mazequest";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            // Do not echo here to avoid HTML before JSON responses.
            $this->conn = null;
        }
        return $this->conn;
    }
}
?>