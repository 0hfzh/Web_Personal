<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geojson to MySQL</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(to right, #6a11cb, #2575fc);
            margin: 0;
            padding: 20px;
            color: #fff;
        }

        h1 {
            text-align: center;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        form {
            background: rgba(255, 255, 255, 0.9);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            margin: auto;
        }

        label {
            display: block;
            margin: 15px 0 5px;
            font-weight: bold;
            color: #333;
        }

        input[type="text"],
        input[type="password"],
        input[type="file"],
        input[type="submit"] {
            width: calc(100% - 20px);
            padding: 10px;
            margin: 5px 0 15px;
            border: 2px solid #6a11cb;
            border-radius: 5px;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: #2575fc;
            outline: none;
        }

        input[type="submit"] {
            background-color: #6a11cb;
            color: white;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s, transform 0.2s;
        }

        input[type="submit"]:hover {
            background-color: #2575fc;
            transform: scale(1.05);
        }

        .error {
            color: red;
            font-weight: bold;
            text-align: center;
        }

        @media (max-width: 600px) {
            form {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<h1>Geojson to MySQL</h1>
<form action="index.php" enctype="multipart/form-data" method="post">
    <label for="host">DB Connection:</label>
    <label for="host">Host:</label>
    <input type="text" name="host" id="host" required> 
    <label for="user">Username:</label>
    <input type="text" name="user" id="user" required>
    <label for="password">Password:</label>
    <input type="password" name="password" id="password">
    <label for="db">DB:</label>
    <input type="text" name="db" id="db" required>
    
    <label for="fileToUpload">Select GeoJson File:</label>
    <input type="file" name="fileToUpload" id="fileToUpload" accept=".geojson" required>
    <input type="submit" value="Convert" name="submit">
</form>

<?php
if(isset($_POST["submit"])) {
    $target_dir = "";
    $originalfile=$_FILES["fileToUpload"]["name"];
    $target_file = $target_dir . basename(time()."_".$_FILES["fileToUpload"]["name"]);
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
    
    //preparing database connection
    $mysqli = new mysqli($_POST["host"],$_POST["user"],$_POST["password"],$_POST["db"]);
    
    // Check connection
    if ($mysqli -> connect_errno) {
      echo "Failed to connect to MySQL: " . $mysqli -> connect_error;
      exit();
    }
    
    
    if($imageFileType != "GEOJSON" && $imageFileType != "geojson" ) {
      echo "Sorry, only geojson files are allowed.";
      $uploadOk = 0;
    } else {
    
            if ($_FILES["fileToUpload"]["size"] > 50000000) {
              echo "Sorry, your file is too large.";
              $uploadOk = 0;
            } else {
                 //save file
                  if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
                    echo "The file ". htmlspecialchars( basename( $_FILES["fileToUpload"]["name"])). " has been uploaded. Size:".$_FILES["fileToUpload"]["size"]."<br>";
                        
                        //save to MySQL Table
                        convertGeojson($target_file,$originalfile,$mysqli);
                  
                  } else {
                    echo "Sorry, there was an error uploading your file.";
                  }			
                
            }
        }
        $mysqli -> close();
    }
    
    
    
    function convertGeojson($file,$tblname,$conn)
    {
        //set tblname
        
        $tblname = str_replace(".geojson","",$tblname);
        $tblname = str_replace(".GEOJSON","",$tblname);
        $tblname = str_replace(" ","_",$tblname);
        
        //read geojson
        $string = file_get_contents($file);
        $json_a = json_decode($string, true);
        
        //reading the attributes
        $properties = $json_a['features'][0]['properties'];
        
        $cols = array();
        $colslist = array();
        $n=0;
        
        //reading the attributes name
        foreach($properties as $key => $val) {
            if ($key) 
            { 
                if(is_numeric ($val)){
                    $cols[$n] = $key." int";
                } else {
                    $cols[$n] = $key." varchar(255)";	
                }
                
                $colslist[$n] = $key;
            }; 
            $n++;
        }
        
        $colsname = implode(",",$cols);	
        $colsnametbl = implode(",",$colslist);	
        
        //formatting sql of table creation
        $sql = "
            CREATE TABLE $tblname (
            OBJID int NOT NULL AUTO_INCREMENT,
            $colsname
            ,SHAPE geometry
            ,PRIMARY KEY (OBJID)
            )	
        ";	
        
        //crate table
        if($conn -> query($sql))
        {
            echo "Table $tblname created.<br>";
            
            $vals = array();
            $v = 0;
            
            //save features
            foreach($json_a['features'] as $feature) {
    
                    $attrs = array();
                    $a = 0;	
                    
                    $tval = $feature;
                    
                    //reading the attributes
                    foreach($tval['properties'] as $key => $val) {
                        
                            $attrs[$a] = "'".$val."'";
                        
                        $a++;
                    }
                    
                    $attrs[$a] = "ST_GeomFromGeoJSON('".json_encode($tval['geometry'])."')";
                 
                    $attributes =  implode(",",$attrs);	
                    
                    $sql = "
                    insert into $tblname($colsnametbl,SHAPE)
                    values ($attributes)
                    
                    ";
                    if($conn -> query($sql))
                    {
                        echo "successfully added feature $v<br>";
                    } else {
                        echo "error adding feature $v<br>$sql<br>";
                    }
                 
                 $v++;
                }	
            
            
        } else {
            echo "Table creation error/duplicate.";
            exit;
        }
    }   
?>
</body>
</html>