<?php

    // set the header as JSON
    header('Content-Type: application/json');

    // main
    function main()
    {
        // import database connection
        include_once('./database/connection.php');
        
        // create mysql object
        $conn = new mysqli($DBServer, $DBUser, $DBPass, $DBName);

        // check connection
        if ($conn->connect_error) {
            die(json_encode(array("success"=>0,"error_message"=>"Unable to contact verification server, please try again.")));
        }
        
        // get the posted lat and lng
        $lat = $_GET["lat"];
        $lng = $_GET["lng"];
        
        // put through escape strings
        $SAFELAT = $conn->real_escape_string($lat);
        $SAFELNG = $conn->real_escape_string($lng);
        
        // get any stations within a square radius
        $station = $conn->query("SELECT *, ( 3959 * acos( cos( radians($SAFELAT) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians($SAFELNG) ) + sin( radians($SAFELAT) ) * sin(radians(latitude)) ) ) AS comp_distance FROM stations INNER JOIN stationInfo_kings ON stations.stationIdentifier = stationInfo_kings.stationIdentifier HAVING comp_distance < 20 ORDER BY comp_distance LIMIT 0 , 10");
        
        $selectedStation = null;
        $stationID = null;
        foreach ($station as $pol)
        {        
            // now query to make sure there is some data for this one -- has to be at least 5 entries in the past 24 hours
            $testForResults = $conn->query('SELECT * FROM `updateRecord` INNER JOIN pollutantRecord ON pollutantRecord.updateIdentifier=updateRecord.updateIdentifier INNER JOIN stationInfo_kings on updateRecord.stationIdentifier=stationInfo_kings.stationIdentifier WHERE timestamp >= NOW() - INTERVAL 24 HOUR AND updateRecord.stationIdentifier = '.$pol["stationIdentifier"].' AND pollutantRecord.airQualityIndex > 0 ORDER BY `pollutantRecord`.`airQualityIndex` LIMIT 10');
            
            $rows = array();
            while($row = $testForResults->fetch_array(MYSQLI_ASSOC))
            {
                $rows[] = $row;
            }
            
            if (count($rows) > 5)
            {
                // it's ok!
                $selectedStation = $pol;
                $stationID = $pol["stationIdentifier"];
                break;
            }
        }
        
        // check we found something
        if ($selectedStation != null)
        {
            // we found a place
            echo json_encode(array("success"=>1, "station"=>$selectedStation, "stationID"=>$stationID));
        }else {
            // nowhere was found
            echo json_encode(array("success"=>0,"error-message"=>"No pollution stations found"));
        }

        // close connection
        $conn->close();
        
    }

    main();

?>