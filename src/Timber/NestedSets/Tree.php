<?php
namespace Timber\NestedSets;

use \Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Resultset\Simple as ResultSet;

final class Tree extends Model 
{
    protected $conn = null;
    public $log     = null;
    
    public $id;
    public $foreign_id;
    
    
    public function initialize()
    {
        $this->hasMany('foreign_id', 'categories', 'id');
        $this->conn = $this->getReadConnection();
    }
    
    public function setLogger($log)
    {
        $this->log = $log;
    }
    
    public function foo()
    {
        $this->log->info("Foo");
    }
    
    /**
     *
     */
    public function addToTree($fkId, $id)
    {
        $sql = 'call addToTree(:fkId, :id, :tableName)';

        
    }
    
    

    /**
     * Returns a category_id path to a given element
     *
     * @param int $id
     * @return array
     */
    public function getPath($id)
    {
        $sql = 
        'SELECT parent.category_id
        FROM category_hierarchy AS child,
        category_hierarchy AS parent
        WHERE child.lft
        BETWEEN parent.lft
        AND parent.rgt
        AND child.ch_id = :id
        ORDER BY parent.lft';
        $x = $this->conn->fetchAll($sql, ['id'=>$id]);
        var_dump($x);
        exit();
        return $x;
    }// end get_path

        /**
         * Returns a path to a given element
         *
         * @param int ch_id matches category hierarchy id in the database
         */
        public function getPathto( $ch_id )
        {
            
                $query = "SELECT parent.ch_id
                          FROM category_hierarchy AS child,
                          category_hierarchy AS parent
                          WHERE child.lft
                          BETWEEN parent.lft
                          AND parent.rgt
                          AND child.ch_id = ".(int)$ch_id."
                          AND parent.lft != 1
                          ORDER BY parent.lft";
                
            }
        }// end get_path


        /**
        * Determines if an id is a leaf node or not
        *
        */
        public function isLeaf( $id )
        {
            

        } // end

        /**
         *
         *
         *
         */
        public function listSubtree($id)
        {

        } //end list tree

        /**
         *
         *
         *
         */
        public function getTree()
        {
        } // end get_tree


        /**
         * Returns the id of the root node
         * (i.e lft = 1)
         *
         * @param none
         * @return int
         */
        public function getRootId()
        {
        } // end get_last_id

        /**
         * Determines if a given id is a valid id
         * represented in the database
         *
         * @param int id
         */
        public function isId($id)
        {
            
        } // end get_last_id

    public static function findByRawSql($sql, $params=null)
    {
      
    }
}