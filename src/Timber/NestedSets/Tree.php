<?php
namespace Timber\NestedSets;

use \Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Resultset\Simple as ResultSet;
use Modules\Products\Models\Categories as CategoriesModel;
use \Timber\Model\Resultset\Object as ObjectResultSet;
use \PDO;
use \Modules\Products\Entities\CategoryCollection;
use \Modules\Products\Entities\PathCollection;

final class Tree extends Model 
{ 
    protected $conn = null;
    public $log     = null;
    
    public $id;
    public $foreign_id;
    public $tableName   = 'category_hierarchy';
    public $lft         = null;
    public $rgt         = null;
    protected $dialect  = null;
    
    
    public function onConstruct()
    {
         // $this->hasOne('foreign_id', '\Modules\Products\Models\Categories', 'id');   
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
            ->columns('child.foreign_id, parent.id, cat.category_name')
            ->addFrom('Timber\NestedSets\Tree', 'child')
            ->addFrom('Timber\NestedSets\Tree', 'parent')
            ->join('\Modules\Products\Models\Categories', 'parent.foreign_id = cat.id', 'cat')
            ->where('child.id = :id:')
            ->andWhere('child.lft BETWEEN parent.lft AND parent.rgt')
            ->orderBy('parent.lft')
            ->getQuery();
            
        $sql = $this->getRawSQLFromBuilder($res);
        
        $objectResultSet = new ResultSet(null, $this, $this->conn->query($sql, $params), null, null);
        $objectResultSet->setHydrateMode(ResultSet::HYDRATE_ARRAYS);
        
        return new PathCollection($objectResultSet);
        
    }

    
    protected function getRawSQLFromBuilder(\Phalcon\Mvc\Model\Query $query)
    {
        if ($this->conn) {
            $dialect = $this->conn->getDialect();
            return $dialect->select($query->parse());
        }
        throw new \ErrorException('No Connection');
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
     * Returns a subtree of a tree
     * 
     * @param int $id    Unique id of the tree
     * @param int $depth levels of the subtree to return
     */
    public function getSubtree($id, $depth = null)
    {

        $depth = $depth ?: 1;
        
        $sql = 'SELECT * FROM 
                (
                    SELECT  node.foreign_id, 
                    node.id as ch_id,(COUNT(parent.foreign_id) - (sub_tree.depth + 1)) AS depth
                    FROM category_hierarchy AS node,
                    category_hierarchy AS parent,
                    category_hierarchy AS sub_parent,
                    (
                            SELECT node.*, (COUNT(parent.id) - 1) AS depth
                            FROM category_hierarchy AS node,
                                category_hierarchy AS parent
                            WHERE node.lft BETWEEN parent.lft AND parent.rgt
                            AND node.id = :id
                            GROUP BY node.id
                            ORDER BY node.lft
                    ) AS sub_tree
                    WHERE node.lft 
                    BETWEEN parent.lft 
                    AND parent.rgt
                    AND node.lft BETWEEN sub_parent.lft 
                    AND sub_parent.rgt 
                    AND sub_parent.id = sub_tree.id GROUP BY node.id 
                    HAVING depth = :depth  
                    ORDER BY node.lft
                ) as ch
                INNER JOIN categories on categories.id = ch.foreign_id
                ORDER BY category_name;';
        $params = [
            'id'    => $id,
            'depth' => 1
        ];
        $types = [
            'id'    => PDO::PARAM_INT,
            'depth' => PDO::PARAM_INT
        ];
        // @todo abstract this step
        $objectResultSet = new ResultSet(
            null, 
            $this, 
            $this->conn->query($sql, $params, $types), 
            null, 
            null
        );
        $objectResultSet->setHydrateMode(ResultSet::HYDRATE_ARRAYS);
        
        return new CategoryCollection($objectResultSet);
    } //end list tree

    /**
    *
    *
    *
    */
    public function getTree()
    {
            $res = $this->_modelsManager->createBuilder()
            ->columns('
                child.foreign_id,
                child.id,
                cat.category_name,
                (COUNT(parent.category_id) - 1) AS depth
            ')
            ->addFrom('Timber\NestedSets\Tree', 'child')
            ->addFrom('Timber\NestedSets\Tree', 'parent')
            ->join('\Modules\Products\Models\Categories', 'parent.foreign_id = cat.id', 'cat')
            ->where('child.id = :id:')
            ->andWhere('child.lft BETWEEN parent.lft AND parent.rgt')
            ->orderBy('parent.lft')
            ->getQuery();
            
        $sql = $this->getRawSQLFromBuilder($res);

    } // end get_tree


    /**
    * Returns the id of the root node
    * (i.e lft = 1)
    *
    * @param none
    * @return int
    */
    public function getRoot()
    {
        $x = $this->findFirst([
            'conditions' => 'lft=1'
        ]);
        return $x;
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
