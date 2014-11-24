<?php
namespace Timber\NestedSets;

use \Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Resultset\Simple as ResultSet;
use Modules\Products\Models\Categories as CategoriesModel;
use \Timber\Model\Resultset\Object as ObjectResultSet;

final class Tree extends Model 
{ 
    protected $conn = null;
    public $log     = null;
    
    public $id;
    public $foreign_id;
    public $tableName   = 'category_hierarchy';
    protected $dialect  = null;
    
    
    public function initialize()
    {
  //      $this->hasOne('foreign_id', '\Modules\Products\Models\Categories', 'id');   
         $this->belongsTo('foreign_id', '\Modules\Products\Models\Categories', 'id', [
            "foreignKey" => [
                "message" => "The part cannot be deleted because other robots are using it"
            ]
        ]);
        $this->conn = $this->getReadConnection();
        
    }
    
    
    
    public function getSource()
    {
        return $this->tableName;
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
        $params = ['id' => $id];
        $res = $this->_modelsManager->createBuilder()
            ->columns('child.foreign_id, child.id, cat.category_name')
            ->addFrom('Timber\NestedSets\Tree', 'child')
            ->addFrom('Timber\NestedSets\Tree', 'parent')
            ->join('\Modules\Products\Models\Categories', 'parent.foreign_id = cat.id', 'cat')
            ->where('child.id = :id:', $params)
            ->andWhere('child.lft BETWEEN parent.lft AND parent.rgt')
            ->orderBy('parent.lft')
            ->getQuery();
            
        $sql = $this->getRawSQLFromBuilder($res);
        
        $x = new ObjectResultSet(null, $this, $this->conn->query($sql, $params), null, null,'Module\Products\Path');
        $x->setHydrateMode(Resultset::HYDRATE_OBJECTS);
        return $x;
        
    }

    
    protected function getRawSQLFromBuilder(\Phalcon\Mvc\Model\Query $query)
    {
        $dialect = $this->conn->getDialect();
        return $dialect->select($query->parse());
    }
    
    protected function getDialect()
    {
        
        
    }
    
    
    
    /**
     * Returns a path to a given element
     *
     * @param int ch_id matches category hierarchy id in the database
     */
    public function getPathTo($ch_id)
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
    
        
    }// end get_path


    /**
    * Determines if an id is a leaf node or not
    *
    */
    public function isLeaf( $id )
    {
        echo "HERE";

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