<?php 

class DB{

  //place the link array in your main config file
  function __construct($link = false){
  

          global $DB;
          
          //if there is no link, used the one your defined in your config
          $link = !$link ? DB_LINK : $link;
         
          //make params accesible within object
          $this->host = $DB[$link]['host'];
          $this->usr  = $DB[$link]['username'];
          $this->pwd  = $DB[$link]['password'];
          $this->db   = $DB[$link]['database'];
          $this->port = $DB[$link]['port'];
          $this->conn = false;
          $this->max_conn_attempts = 3;
          $this->conn_attempts = 0;
       
  }
  
  
  function is_connected(){
  
    return $this->conn;
    
  }
  
  //connect function
  function connect(){
  
  
     //check to see if they can connect
    try{
      
      //set the conn variable
      return $this->conn = new PDO(
        'mysql:'.
        'host='.$this->host.';'.
        'port='.$this->port.';'.
        'dbname='.$this->db, 
          $this->usr, 
          $this->pwd,
          array(
         // PDO::ATTR_EMULATE_PREPARES => false, 
            PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false
          )
       );
      //--> end of connection

      
    }catch(PDOException  $e){//if they cant connect retry
            

    return $e;

       //if they are over the max amount of reconnect attempts then die     
      if($this->conn_attempts >= $this->max_conn_attempts){
        
        //this is the standard return code message
        return array(
          'error' => array(
            'code' => 0,
            'msg' => 'COULD NOT CONNECT'
          )
        );
      }
      //increment the connection amount
        $this->conn_attempts++;
        
        //sleep a little before the next attemp
        usleep(100 * 10000);
        
        //try again!
        return $this->connect();

            
      
    }
    //--> end of try/catch connect
      
    

  }
  //--> end of connect function
  
  
  function query($sql){
    

    //check if connected 
    if( !$this->is_connected()){
    
       //try to connect
       $connect = $this->connect();
      
      //if there's an error return it
       if(isset($connect->error)) 
              return $connect;
      
    }

   
            try{   
                    
            return $this->conn->query($sql);
          }catch(PDOException $e){
        
          //return the error
          return self::parseError($e);
        
      }
    
  }
  
  function select($sql = false, $params = false, $row = false, $index = false){
      

      
    //check if connected 
    if( !$this->is_connected()){
    
       //try to connect
       $connect = $this->connect();
      
      //if there's an error return it
       if(isset($connect->error)) 
              return $connect;
      
    }

    //if there are no params provided
    if(!$params) $params = array();

   // return $params;
      //attempt the select query
    try{
        
        
        
        //prepare the statement
        $stmt = $this->conn->prepare($sql);
        
        //execute
        $stmt->execute( $params );
        
        //check to see if it is for a single or multiple rows, we will use this var as the function name
         $fetch = 'fetch';
         if(!$row) $fetch .= 'All';
         
         
         
           //return $fetch;
          
          //check to see if they want the result set grouped by an index name
          if(!$index){
            $arg = PDO::FETCH_ASSOC;
          }else{
            $arg = PDO::FETCH_GROUP|PDO::FETCH_ASSOC;
          }
          
          //fetch the result(s)
          $result = $stmt->$fetch($arg);
        
          //this is to fix the nested array 
          if($index && !$row){
            $result = array_map('reset', $result);
          }
        
       
        
        return $result;

     }catch(PDOException $e){
        
        //return the error
        return self::parseError($e);
        
     }
     //--> end of try/catch for the select statement

    
  }
  //--> end of select function
  
  
  
  
  
  function insert($table, $data, $last_insert_id = false, $multiple = false){
  
     
      //check if connected 
     if( !$this->is_connected()){
        $this->connect();
     }
      
          //return ($data);  
      //if it's a multiple insert
      if($multiple){
         
          $n = array();
          $fields = array();
          $binds = array();
          
          foreach($data as $d){
             
            $n[] = $a = self::prepData($d);
            
            $fields[] = '('. implode(',', ($a['vals'])) . ')';

            foreach($a['binds'] as $bind){
              $binds[] = $bind;
            }
            
          }
          
         

        $sql = "INSERT INTO " . $table . " (" . implode(",", $n[0]['cols']) . ") VALUES " . implode(',', $fields) . ";"; 
         
   
      }else{
     
       //prep the data
        $a = self::prepData($data);
      
       //put into sql
        $sql = "INSERT INTO " . $table . " (" . implode(",", $a['cols']) . ") VALUES (" . implode(',', ($a['vals'])) . ")";
        
        $binds = $a['binds'];
        
      
    }
     //put the values as parameters, this is only needed for data input but we may as well just use it for everything
    
      
      //attempt the insert query
      try{
        
        //prepare the statement
        $stmt = $this->conn->prepare($sql);
        $result = $stmt->execute( $binds );
        
        //if last_insert_id is true return the insert ID
        if($last_insert_id){
          return $this->conn->lastInsertId();
        }else{
          return $result;
        }
       
       
      }catch(PDOException $e){
        
        //return the error
        return self::parseError($e);
        
      }
      //--> end of the try/catch insert query
      
  }
  //--> end of insert function
  
  
  function update($table_name, $data, $condition, $insert_select = false){
  
         //check if connected 
         if( !$this->is_connected()){
            $this->connect();
         }
  
        
  
        // process the incoming data and get a list of columns and values
        $a = db::prepData($data);
    	
        $count = count($a['cols']);
		
        
        //return $a;
        for ($i=0; $i<$count; $i++) {
            $updates[] = $a['cols'][$i] . '=' . $a['vals'][$i];
        }
          
          
         // return $updates;
         $binds = $a['binds'];
        

        if($insert_select){
           
          //we add ignore just in case 
          $sql = "INSERT IGNORE INTO $table_name (" . implode(",", $a['cols']) . ") VALUES (" . implode(",", $a['vals']) . ") ON DUPLICATE KEY UPDATE " . implode(",", $updates) . ";";
          
          //we do this since we have the exact same amount of binds
          $binds = array_merge($binds, $binds);
           
        }else{
        
          $sql = "UPDATE $table_name SET " . implode(",", $updates) . " WHERE $condition;";
        
        }
		

      //attempt the insert query
      try{
        
        //prepare the statement
        $stmt = $this->conn->prepare($sql);
        $result = $stmt->execute( $binds );
        
        //if last_insert_id is true return the insert ID
       // if($last_insert_id){
       //   return $this->conn->lastInsertId();
       // }else{
          return $result;
       // }
       
       
      }catch(PDOException $e){
        
        
       // return $e->getMessage();
        //return the error
        return self::parseError($e);
        
      }
      //--> end of the try/catch insert query
        
        $data = array(
          'sql' => $sql,
          'a' => $a
        );
        

    }

  //--> end of update function 
  
  
  
  
  
  function parseError($e){
      
      return array(
          'error' => array(
              'code' => $e->errorInfo[1],
              'msg' => $e->getMessage()
          )
        );
      
  }

        
        
        
      function prepData(&$data){

            //init
            $cols  = array();
            $vals  = array();
            $binds = array();
            $params = array();
            // loop thru each row of data
            foreach ($data as $col => $val) {
                
                //if there is an @ it means that it's a MYSQL command and should not be binded
                if ($col[0] == '@') {
                    
                    //remove the @
                    $col = substr($col,1);
                    
                    //add it to bind
                   // $vals[] = $val;
                    $vals[] = $val;
                } else {
                    //this means we want the data to be sanitized
                   // $binds[] = ':'.$col;
                    $vals[] = '?';
                    $binds[] = $val;
                    
                  //  $binds[] = '?';
                   //these are the values to bind with  
                }
                
                //this will always be the columns
                $cols[] = $col;
                
            }
            //return 
            return array ('cols' => $cols, 'vals' => $vals, 'binds' => $binds);
        }
        //--> end of prep data
}
?>
