<?php
namespace Vendor\Factories;

/**
 * The singleton class for a MySQL PDO connection
 * User: ROUSSEAU Alexy
 */
class PDOMySQL
{
    private static $myPdo = null;
    private static $instance = null;

    /**
     * Private constructor called only one time (singleton)
     */
    private function __construct()
    {
        try {
            self::$myPdo = new \PDO(
                SERVER . ';' . DB,
                USER,
                PASSWORD
            );
            self::$myPdo->query('SET CHARACTER SET utf8');
        } catch (Exception $e) {
            die('Error : ' . $e->getMessage());
        }
    }

    /**
     * Destructor, called when the class reference is removed.
     */
    public function __destruct()
    {
        self::$myPdo = null;
    }

    /**
     * Static caller for the class (singleton)
     *
     * @return PDOMysql the only instance of the class
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new PDOMySQL();
        }

        return self::$myPdo;
    }
}