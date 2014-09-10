DELIMITER $$;

DROP TABLE IF EXISTS category_hierarchy;

CREATE TABLE category_hierarchy(
   id int(100) AUTO_INCREMENT PRIMARY KEY,
   foreign_id  int(3) NOT NULL,
   lft int(100) NOT NULL,
   rgt int (100) NOT NULL
);


DROP procedure IF EXISTS isTreeId;
CREATE PROCEDURE isTreeId(IN id INT, IN TableName varchar(255), OUT myid INT) CONTAINS SQL
BEGIN
    SET @myid    = NULL;
    SET @dyn_sql = CONCAT('SELECT id INTO @myid FROM ',TableName,' WHERE id = ?');

    PREPARE stmt from @dyn_sql;
    SET @tree_id=id;
    EXECUTE stmt USING @tree_id;
    SET myid := @myid;
    DEALLOCATE PREPARE stmt;
END;

DROP procedure IF EXISTS isRootNode;
CREATE PROCEDURE isRootNode(IN id INT, IN TableName varchar(255), OUT myid BOOLEAN) CONTAINS SQL
BEGIN
    SET @myid = NULL;
    SET @dyn_sql=CONCAT('SELECT id INTO @myid FROM ',TableName,' WHERE lft = 1');
    PREPARE stmt from @dyn_sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
    
    IF @myid = id THEN 
       SET myid := true;
    ELSE 
       SET myid := false;
    END IF;
    
END;



DROP PROCEDURE IF EXISTS addToTreeRoot;
CREATE PROCEDURE addToTreeRoot(IN ForeignId int, IN TableName varchar(255),OUT myid INT) CONTAINS SQL
BEGIN

    SET @dyn_sql=CONCAT('INSERT INTO ',TableName,'(foreign_id,lft,rgt) VALUES (?,1,2)');

    PREPARE stmt from @dyn_sql;
    SET @fId=ForeignId;
    EXECUTE stmt USING @fId;
    SELECT last_insert_id() INTO myid;
    DEALLOCATE PREPARE stmt;
END;


DROP procedure IF EXISTS addToLeaf;
CREATE PROCEDURE addToLeaf( IN ForeignId INT,IN ParentId INT,IN TableName varchar(255), OUT myid INT ) CONTAINS SQL
BEGIN

    DECLARE myLft integer;
    DECLARE newId integer;

    -- get the left value for the row with the id
    SET @dyn_sql=CONCAT('SELECT lft INTO @myLft FROM ',TableName,' WHERE id = ?');
    PREPARE stmt from @dyn_sql;
    SET @pId=ParentId;
    EXECUTE stmt USING @pId;
    DEALLOCATE PREPARE stmt;
    
    -- make space for the insert

    -- update everything to the right
    SET @dyn_sql = CONCAT('UPDATE ',TableName, ' SET rgt = rgt+2 WHERE rgt > ?');
    PREPARE stmt from @dyn_sql;
    EXECUTE stmt using @myLft;
    DEALLOCATE PREPARE stmt;

    -- update everything to the lft
    SET @dyn_sql = CONCAT('UPDATE ',TableName, ' SET lft = lft + 2 WHERE lft > ?');
    PREPARE stmt from @dyn_sql;
    EXECUTE stmt using @myLft;
    DEALLOCATE PREPARE stmt;

    
    -- now we have space create the record
    SET @dyn_sql = CONCAT('INSERT INTO ',TableName,' (foreign_id, lft, rgt) VALUES(?, ?,?)');
    PREPARE stmt from @dyn_sql;
    SET @fid=ForeignId;
    SET @lft := @myLft + 1;
    SET @rgt := @myLft + 2;
    EXECUTE stmt USING @fid,@lft,@rgt;
    SELECT last_insert_id() INTO myid;    
    DEALLOCATE PREPARE stmt;
    
END;

DROP procedure IF EXISTS addToBranch;
CREATE PROCEDURE addToBranch( IN ForeignId INT, IN ParentId INT, IN TableName varchar(255), OUT myid INT ) CONTAINS SQL
BEGIN
    SET @myRgt :=0;

    SET @dyn_sql=CONCAT('SELECT rgt-1 INTO @myRgt FROM ',TableName,' WHERE foreign_id = ?');
    PREPARE stmt FROM @dyn_sql;
    SET @fid=ParentId;
    EXECUTE stmt USING @fid;
    DEALLOCATE PREPARE stmt;
   
    IF @myRgt > 0 THEN
                
        SET @dyn_sql=CONCAT('UPDATE ',TableName,' SET rgt = rgt + 2 WHERE rgt > @myRgt= ?');
        PREPARE stmt FROM @dyn_sql;
        EXECUTE stmt USING @myRgt;
        DEALLOCATE PREPARE stmt;

        SET @dyn_sql=CONCAT('INSERT INTO ',TableName,' (foreign_id, lft, rgt) VALUES( ? , myRgt+1 , myRgt+2 );');

        PREPARE stmt FROM @dyn_sql;
        SET @fid=ForeignId;
        EXECUTE stmt USING @fid;
        DEALLOCATE PREPARE stmt;

        SELECT last_insert_id() INTO myid;

    ELSE
        SET @newId = -1;
    END IF;
END;



DROP PROCEDURE IF EXISTS addToTree;
CREATE PROCEDURE addToTree(IN ForeignId INT, IN ParentId INT, IN TableName varchar(255), OUT myid INT ) CONTAINS SQL
BEGIN
    SET @isRoot :=NULL;
    SET @isId   :=NULL;
    -- @todo check to see if it's a real id 
    call isTreeId(ParentId, TableName, @isId);

    -- call isRootNode(ParentId, TableName, @isRoot);
    -- IF @isRoot IS NOT NULL AND @isRoot = TRUE THEN
    IF @isId IS NOT NULL THEN
        SET @isLeaf := FALSE;

        call isLeaf(ParentId, TableName, @isLeaf );
        
        IF @isLeaf > 0 THEN
            call addToLeaf(ForeignId, ParentId, TableName, @newId);
        ELSE
            call addToBranch( ForeignId, ParentId, TableName, @newId);
        END IF;

    ElSE
        SET @foreignId :=ForeignId;
        select @foreignId as foo;

        call addToTreeRoot( ForeignId, TableName, @newId );
    END IF;
    SET myid := @newId;
END;

DROP PROCEDURE IF EXISTS isLeaf;
CREATE PROCEDURE isLeaf( IN id int, IN TableName varchar(255),OUT outId BOOLEAN) CONTAINS SQL
    BEGIN
    DECLARE myid BOOLEAN;

    SET @dyn_sql=CONCAT('SELECT id INTO @myid FROM ',TableName,' WHERE rgt = lft + 1 AND id = ?');
    PREPARE stmt FROM @dyn_sql;
    SET @fid=id;
    EXECUTE stmt USING @fid;
    SET outId := @myid;
    DEALLOCATE PREPARE stmt;
END;


DROP procedure IF EXISTS listAsTree;
CREATE PROCEDURE listAsTree( IN TableName varchar(255)) CONTAINS SQL
BEGIN
    SET @dyn_sql=CONCAT('SELECT node.id, CONCAT( REPEAT(" ", COUNT(parent.id) - 1), node.foreign_id) AS foreign_id FROM ',TableName,' AS node, ',TableName,' AS parent WHERE node.lft BETWEEN parent.lft AND parent.rgt GROUP BY node.foreign_id ORDER BY node.lft;');
    PREPARE stmt FROM @dyn_sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;

END;
DROP procedure IF EXISTS removeNode;
CREATE PROCEDURE removeNode( IN id int,IN TableName varchar(255) ) CONTAINS SQL
BEGIN

    SET @myLeft  := 0;
    SET @myRight := 0;

    SET @dyn_sql=CONCAT('SELECT @myLeft := lft, @myRight := rgt, @myWidth := rgt - lft + 1 FROM ',TableName,' WHERE id = ?');
    PREPARE stmt FROM @dyn_sql;
    SET @id=id;
    EXECUTE stmt USING @id;
    DEALLOCATE PREPARE stmt;

    IF @myLeft > 0 THEN

        SET @dyn_sql=CONCAT('DELETE FROM ',TableName,' WHERE lft BETWEEN @myLeft AND @myRight');
        PREPARE stmt FROM @dyn_sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;

        SET @dyn_sql=CONCAT('UPDATE ',TableName,' SET rgt = rgt - @myWidth WHERE rgt > @myRight');
        PREPARE stmt FROM @dyn_sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;

        SET @dyn_sql=CONCAT('UPDATE ',TableName,' SET lft = lft - @myWidth WHERE lft > @myRight');
        PREPARE stmt FROM @dyn_sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END;

$$;
DELIMITER ;
call addToTreeRoot(10,'category_hierarchy', @level2);


-- foreignID ParentID 
call addToTree(16, @level2, 'category_hierarchy', @lastId);


-- add a new entries of 3 and 8 to the same level
call addToTree(3, @level2,'category_hierarchy', @level3);

  call addToTree(81, @level3,'category_hierarchy',@level4);
  call addToTree(82, @level3,'category_hierarchy',@level4);
  call addToTree(83, @level3,'category_hierarchy',@level4);
  call addToTree(84, @level3,'category_hierarchy',@level4);
  call addToTree(85, @level3,'category_hierarchy',@level4);

call addToTree(30, @level2,'category_hierarchy', @level3);
  call addToTree(31, @level3,'category_hierarchy', @level4);
  call addToTree(32, @level3,'category_hierarchy', @level4);
  call addToTree(33, @level3,'category_hierarchy', @level4);
  call addToTree(34, @level3,'category_hierarchy', @level4);

-- show the tree
call listAsTree('category_hierarchy');

call removeNode(12,'category_hierarchy');
call addToTree(36,9,'category_hierarchy',@level3);
call listAsTree('category_hierarchy');

-- call removeNode(9,'category_hierarchy');
call listAsTree('category_hierarchy');
-- SELECT @chId;

-- SELECT @newId;
-- call addToTree(75,@newId,'category_hierarchy',@newId);

-- call addToTree(4,@chId,'category_hierarchy',@newId);
--

-- SELECT @myid;
-- call addToLeaf(@chid,10,'category_hierarchy',@myid);
-- SELECT @myid;
-- call addToBranch(@chid,14,'category_hierarchy',@myid);
-- call addToTree('11',@myid,'category_hierarchy',@newId);

-- call removeNode('11','category_hierarchy');
-- call listAsTree('category_hierarchy');


