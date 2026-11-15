<?php

namespace Core;
use \PDO;
use \PDOException;

defined('ROOT') or die("Direct script access denied");

/**
 * Database class
 */
#[\AllowDynamicProperties]
class Database
{
    // Make the connection property static to hold a single instance
    private static ?PDO $con = null; 

    public static $query_id      = '';
    public $affected_rows        = 0;
    public $insert_id            = 0;
    public $error                = '';
    public $has_error            = false;
    public $table_exists_db      = '';
    public $missing_tables       = [];

    // The connect method now checks if a connection already exists
    private function connect(): PDO
    {
        // If a connection has already been established, return it
        if (self::$con) {
            return self::$con;
        }

        $VARS['DB_NAME']         = DB_NAME;
        $VARS['DB_USER']         = DB_USER;
        $VARS['DB_PASSWORD']     = DB_PASSWORD;
        $VARS['DB_HOST']         = DB_HOST;
        $VARS['DB_DRIVER']         = DB_DRIVER;

        $VARS = do_filter('before_db_connect',$VARS);
        $this->table_exists_db = $VARS['DB_NAME'];

        $string = "$VARS[DB_DRIVER]:host=$VARS[DB_HOST];dbname=$VARS[DB_NAME]";

        try
        {
            // Create the new connection and store it in the static property
            $con = new PDO($string,$VARS['DB_USER'],$VARS['DB_PASSWORD']);
            $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$con = $con;

        } catch (PDOException $e) {
            if (function_exists('render_app_database_failure')) {
                render_app_database_failure($e);
            }

            throw $e;
        }

        return self::$con;
    }

    // --- All other methods remain largely the same, but the call to $this->connect()
    // --- will now return the same connection object for the duration of the request.

    /**
     * Fetch multiple rows (assoc by default)
     */
    public function run(string $query, array $data = [], string $data_type = 'assoc'): array
    {
        $result = $this->query($query, $data, $data_type);
        return is_array($result) ? $result : [];
    }

    /**
     * Fetch single row
     */
    public function runOne(string $query, array $data = [], string $data_type = 'assoc')
    {
        $rows = $this->query($query, $data, $data_type);
        if (is_array($rows) && count($rows) > 0) {
            return $rows[0];
        }
        return false;
    }

    public function get_row(string $query, array $data = [], string $data_type = 'object')
    {
        return $this->runOne($query, $data, $data_type);
    }

    public function query(string $query, array $data = [], string $data_type = 'object')
    {
        $query = do_filter('before_query_query',$query);
        $data = do_filter('before_query_data',$data);

        $this->error              = '';
        $this->has_error            = false;

        // The key change is here: this now gets a single, existing connection
        $con = $this->connect();

        try
        {
            $stm = $con->prepare($query);

            $result = $stm->execute($data);
            $this->affected_rows      = $stm->rowCount();
            $this->insert_id          = $con->lastInsertId();

            if($result)
            {
                if($data_type == 'object'){
                    $rows = $stm->fetchAll(PDO::FETCH_OBJ);
                }else{
                    $rows = $stm->fetchAll(PDO::FETCH_ASSOC);
                }
            }

        }catch(PDOException $e)
        {
            $this->error              = $e->getMessage();
            $this->has_error          = true;
        }

        $arr = [];
        $arr['query'] = $query;
        $arr['data'] = $data;
        $query_id = self::$query_id;

        if($query_id == 'get-users' && !empty($rows) && is_array($rows))
        {
            foreach ($rows as $k => $r) {
                if(!is_object($r)){
                    continue;
                }
                if(!property_exists($r, 'id'))
                {
                    $rows[$k]->id = property_exists($r, 'user_id') ? ($r->user_id ?? 0) : 0;
                }
            }
        }

        $arr['result'] = $rows ?? [];
        $arr['query_id'] = $query_id;
        self::$query_id = '';

        $result = do_filter('after_query',$arr);

        if(is_array($result['result']) && count($result['result']) > 0)
        {
            return $result['result'];
        }

        return false;
    }

    /**
     * Close the database connection to free up resources
     * Call this at the end of each request to prevent "Too many connections" errors
     */
    public static function close(): void
    {
        self::$con = null;
    }

    public function beginTransaction(): bool
    {
        $this->error = '';
        $this->has_error = false;

        try {
            $con = $this->connect();
            if ($con->inTransaction()) {
                return true;
            }
            return $con->beginTransaction();
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            $this->has_error = true;
            return false;
        }
    }

    public function commit(): bool
    {
        $this->error = '';
        $this->has_error = false;

        try {
            $con = $this->connect();
            if (!$con->inTransaction()) {
                return true;
            }
            return $con->commit();
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            $this->has_error = true;
            return false;
        }
    }

    public function rollBack(): bool
    {
        $this->error = '';
        $this->has_error = false;

        try {
            $con = $this->connect();
            if (!$con->inTransaction()) {
                return true;
            }
            return $con->rollBack();
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            $this->has_error = true;
            return false;
        }
    }

    public function inTransaction(): bool
    {
        try {
            return $this->connect()->inTransaction();
        } catch (PDOException $e) {
            return false;
        }
    }

    public function table_exists(string|array $mytables):bool
    {
        global $APP;
        $this->missing_tables = [];

        if(empty($APP['tables']))
        {

            $this->error              = '';
            $this->has_error            = false;

            // This call also now uses the single existing connection
            $con = $this->connect();

            $query = "SELECT TABLE_NAME AS tables FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '".$this->table_exists_db."'";
 
            $res = $this->query($query);
            $result = $APP['tables'] = $res;
        }else
        {
            $result = $APP['tables'];
        }
        
        if($result)
        {
            $all_tables = array_column($result, 'tables');

            if(is_string($mytables))
                $mytables = [$mytables];

            $count = 0;
            foreach ($mytables as $key => $table) {
                if(in_array($table, $all_tables)){
                    $count++;
                }else{
                    $this->missing_tables[] = $table;
                }
            }
            
            if($count == count($mytables))
                return true;
        }

        return false;
    }
}
