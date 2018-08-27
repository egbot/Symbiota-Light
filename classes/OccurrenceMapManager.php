<?php
include_once('OccurrenceManager.php');
include_once('OccurrenceAccessStats.php');

class OccurrenceMapManager extends OccurrenceManager {

	private $recordCount = 0;
	private $googleIconArr = Array();
	private $collArrIndex = 0;

	public function __construct(){
		parent::__construct();
		$this->googleIconArr = array('pushpin/ylw-pushpin','pushpin/blue-pushpin','pushpin/grn-pushpin','pushpin/ltblu-pushpin',
			'pushpin/pink-pushpin','pushpin/purple-pushpin', 'pushpin/red-pushpin','pushpin/wht-pushpin','paddle/blu-blank',
			'paddle/grn-blank','paddle/ltblu-blank','paddle/pink-blank','paddle/wht-blank','paddle/blu-diamond','paddle/grn-diamond',
			'paddle/ltblu-diamond','paddle/pink-diamond','paddle/ylw-diamond','paddle/wht-diamond','paddle/red-diamond','paddle/purple-diamond',
			'paddle/blu-circle','paddle/grn-circle','paddle/ltblu-circle','paddle/pink-circle','paddle/ylw-circle','paddle/wht-circle',
			'paddle/red-circle','paddle/purple-circle','paddle/blu-square','paddle/grn-square','paddle/ltblu-square','paddle/pink-square',
			'paddle/ylw-square','paddle/wht-square','paddle/red-square','paddle/purple-square','paddle/blu-stars','paddle/grn-stars',
			'paddle/ltblu-stars','paddle/pink-stars','paddle/ylw-stars','paddle/wht-stars','paddle/red-stars','paddle/purple-stars');
		$this->readGeoRequestVariables();
		$this->setGeoSqlWhere();
		$this->setRecordCnt();
	}

	public function __destruct(){
		parent::__destruct();
	}

	private function readGeoRequestVariables(){
		if(array_key_exists("gridSizeSetting",$_REQUEST)){
			$this->searchTermArr["gridSizeSetting"] = $this->cleanInStr($_REQUEST["gridSizeSetting"]);
		}
		if(array_key_exists("minClusterSetting",$_REQUEST)){
			$this->searchTermArr["minClusterSetting"] = $this->cleanInStr($_REQUEST["minClusterSetting"]);
		}
		if(array_key_exists("clusterSwitch",$_REQUEST)){
			$this->searchTermArr["clusterSwitch"] = $this->cleanInStr($_REQUEST["clusterSwitch"]);
		}
		if(array_key_exists("recordlimit",$_REQUEST)){
			if(is_numeric($_REQUEST["recordlimit"])){
				$this->searchTermArr["recordlimit"] = $_REQUEST["recordlimit"];
			}
		}
		if(array_key_exists("cltype",$_REQUEST) && $_REQUEST["cltype"]){
			$this->searchTermArr["cltype"] = $_REQUEST["cltype"];
		}
		if(array_key_exists("poly_array",$_REQUEST) && $_REQUEST["poly_array"]){
			$this->searchTermArr["polycoords"] = $_REQUEST["poly_array"];
		}
		elseif(array_key_exists("polycoords",$_REQUEST) && $_REQUEST["polycoords"]){
			$this->searchTermArr["polycoords"] = $_REQUEST["polycoords"];
		}
		elseif($this->getClFootprintWkt()){
			$this->searchTermArr["polycoords"] = $this->getClFootprintWkt();
		}
	}

	//Coordinate retrival function
	public function getCoordinateMap($start, $limit){
		$coordArr = Array();
		if($this->sqlWhere){
			$statsManager = new OccurrenceAccessStats();
			$sql = 'SELECT o.occid, CONCAT_WS(" ",o.recordedby,IFNULL(o.recordnumber,o.eventdate)) AS identifier, '.
				'o.sciname, o.family, o.tidinterpreted, o.DecimalLatitude, o.DecimalLongitude, o.collid, o.catalognumber, '.
				'o.othercatalognumbers, c.institutioncode, c.collectioncode, c.CollectionName '.
				'FROM omoccurrences o LEFT JOIN omcollections c ON o.collid = c.collid ';
			$sql .= $this->getTableJoins($this->sqlWhere);
			$sql .= $this->sqlWhere;
			if(is_numeric($start) && $limit){
				$sql .= "LIMIT ".$start.",".$limit;
			}
			//echo "<div>SQL: ".$sql."</div>";
			$result = $this->conn->query($sql);
			$color = 'e69e67';
			while($row = $result->fetch_object()){
				if(($row->DecimalLongitude <= 180 && $row->DecimalLongitude >= -180) && ($row->DecimalLatitude <= 90 && $row->DecimalLatitude >= -90)){
					$occId = $row->occid;
					$collName = $row->CollectionName;
					$tidInterpreted = $this->htmlEntities($row->tidinterpreted);
					$latLngStr = $row->DecimalLatitude.",".$row->DecimalLongitude;
					$coordArr[$collName][$occId]["llStr"] = $latLngStr;
					$coordArr[$collName][$occId]["collid"] = $this->htmlEntities($row->collid);
					//$tidcode = strtolower(str_replace(" ", "",$tidInterpreted.$row->sciname));
					//$tidcode = preg_replace( "/[^A-Za-z0-9 ]/","",$tidcode);
					//$coordArr[$collName][$occId]["ns"] = $this->htmlEntities($tidcode);
					$coordArr[$collName][$occId]["tid"] = $tidInterpreted;
					$coordArr[$collName][$occId]["fam"] = ($row->family?strtoupper($row->family):'undefined');
					$coordArr[$collName][$occId]["sn"] = $row->sciname;
					$coordArr[$collName][$occId]["id"] = $this->htmlEntities($row->identifier);
					//$coordArr[$collName][$occId]["icode"] = $this->htmlEntities($row->institutioncode);
					//$coordArr[$collName][$occId]["ccode"] = $this->htmlEntities($row->collectioncode);
					//$coordArr[$collName][$occId]["cn"] = $this->htmlEntities($row->catalognumber);
					//$coordArr[$collName][$occId]["ocn"] = $this->htmlEntities($row->othercatalognumbers);
					$coordArr[$collName]["c"] = $color;
					$statsManager->recordAccessEvent($occId, 'map');
				}
			}
			if(array_key_exists("undefined",$coordArr)){
				$coordArr["undefined"]["c"] = $color;
			}
			$result->free();
		}
		return $coordArr;
	}

	//Occurrence functions
	public function getOccurrenceArr($pageRequest,$cntPerPage){
		$retArr = Array();
		if($this->sqlWhere){
			$sql = 'SELECT o.occid, c.institutioncode, o.catalognumber, CONCAT_WS(" ",o.recordedby,o.recordnumber) AS collector, '.
				'o.eventdate, o.family, o.sciname, CONCAT_WS("; ",o.country, o.stateProvince, o.county) AS locality, o.DecimalLatitude, o.DecimalLongitude, '.
				'IFNULL(o.LocalitySecurity,0) AS LocalitySecurity, o.localitysecurityreason '.
				'FROM omoccurrences o LEFT JOIN omcollections c ON o.collid = c.collid ';
			$sql .= $this->getTableJoins($this->sqlWhere);
			$sql .= $this->sqlWhere;
			$bottomLimit = ($pageRequest - 1)*$cntPerPage;
			$sql .= "ORDER BY o.sciname, o.eventdate ";
			$sql .= "LIMIT ".$bottomLimit.",".$cntPerPage;
			//echo "<div>Spec sql: ".$sql."</div>";
			$result = $this->conn->query($sql);
			while($r = $result->fetch_object()){
				$occId = $r->occid;
				$retArr[$occId]['i'] = $this->cleanOutStr($r->institutioncode);
				$retArr[$occId]['cat'] = $this->cleanOutStr($r->catalognumber);
				$retArr[$occId]['c'] = $this->cleanOutStr($r->collector);
				$retArr[$occId]['e'] = $this->cleanOutStr($r->eventdate);
				$retArr[$occId]['f'] = $this->cleanOutStr($r->family);
				$retArr[$occId]['s'] = $this->cleanOutStr($r->sciname);
				$retArr[$occId]['l'] = $this->cleanOutStr($r->locality);
				$retArr[$occId]['lat'] = $this->cleanOutStr($r->DecimalLatitude);
				$retArr[$occId]['lon'] = $this->cleanOutStr($r->DecimalLongitude);
				$retArr[$occId]['l'] = str_replace('.,',',',$r->locality);
			}
			$result->free();
			//Set access statistics
			if($retArr){
				$statsManager = new OccurrenceAccessStats();
				$statsManager->recordAccessEventByArr(array_keys($retArr),'list');
			}
		}
		return $retArr;
	}

	private function setRecordCnt(){
		if($this->sqlWhere){
			$sql = "SELECT COUNT(o.occid) AS cnt FROM omoccurrences o ".$this->getTableJoins($this->sqlWhere).$this->sqlWhere;
			//echo "<div>Count sql: ".$sql."</div>";
			$result = $this->conn->query($sql);
			if($result){
				if($row = $result->fetch_object()){
					$this->recordCount = $row->cnt;
				}
				$result->free();
			}
		}
	}

	public function getRecordCnt(){
		return $this->recordCount;
	}

	//SQL where functions
	private function setGeoSqlWhere(){
		global $USER_RIGHTS;
		if($this->searchTermArr){
			$sqlWhere = $this->getSqlWhere();
			$sqlWhere .= ($sqlWhere?'AND ':'WHERE ').'(o.DecimalLatitude IS NOT NULL AND o.DecimalLongitude IS NOT NULL) ';
			if(array_key_exists('clid',$this->searchTermArr) && $this->searchTermArr['clid']){
				if(isset($this->searchTermArr["cltype"]) && $this->searchTermArr["cltype"] == 'all'){
					$sqlWhere .= "AND (ST_Within(p.point,GeomFromText('".$this->getClFootprintWkt()." '))) ";
				}
				else{
					//$sqlWhere .= "AND (v.clid IN(".$this->searchTermArr['clid'].")) ";
				}
			}
			elseif(array_key_exists("polycoords",$this->searchTermArr)){
				$sqlWhere .= "AND (ST_Within(p.point,GeomFromText('".$this->searchTermArr["polycoords"]." '))) ";
			}
			//Check and exclude records with sensitive species protections
			if(array_key_exists("SuperAdmin",$USER_RIGHTS) || array_key_exists("CollAdmin",$USER_RIGHTS) || array_key_exists("RareSppAdmin",$USER_RIGHTS) || array_key_exists("RareSppReadAll",$USER_RIGHTS)){
				//Is global rare species reader, thus do nothing to sql and grab all records
			}
			elseif(array_key_exists("RareSppReader",$USER_RIGHTS)){
				$sqlWhere .= " AND (o.CollId IN (".implode(",",$USER_RIGHTS["RareSppReader"]).") OR (o.LocalitySecurity = 0 OR o.LocalitySecurity IS NULL)) ";
			}
			else{
				$sqlWhere .= " AND (o.LocalitySecurity = 0 OR o.LocalitySecurity IS NULL) ";
			}
			$this->sqlWhere = $sqlWhere;
			//echo '<div style="margin-left:10px">sql: '.$this->sqlWhere.'</div>'; exit;
		}
	}

	//Shape functions
	public function createShape($previousCriteria){
		$queryShape = '';
		$properties = 'strokeWeight: 0,';
		$properties .= 'fillOpacity: 0.45,';
		$properties .= 'editable: true,';
		//$properties .= 'draggable: true,';
		$properties .= 'map: map});';

		if(isset($previousCriteria["upperlat"]) && $previousCriteria["upperlat"]){
			$queryShape = 'var queryRectangle = new google.maps.Rectangle({';
			$queryShape .= 'bounds: new google.maps.LatLngBounds(';
			$queryShape .= 'new google.maps.LatLng('.$previousCriteria["bottomlat"].', '.$previousCriteria["leftlong"].'),';
			$queryShape .= 'new google.maps.LatLng('.$previousCriteria["upperlat"].', '.$previousCriteria["rightlong"].')),';
			$queryShape .= $properties;
			$queryShape .= "queryRectangle.type = 'rectangle';";
			$queryShape .= "google.maps.event.addListener(queryRectangle, 'click', function() {";
			$queryShape .= 'setSelection(queryRectangle);});';
			$queryShape .= "google.maps.event.addListener(queryRectangle, 'dragend', function() {";
			$queryShape .= 'setSelection(queryRectangle);});';
			$queryShape .= "google.maps.event.addListener(queryRectangle, 'bounds_changed', function() {";
			$queryShape .= 'setSelection(queryRectangle);});';
			$queryShape .= 'setSelection(queryRectangle);';
			$queryShape .= 'var queryShapeBounds = new google.maps.LatLngBounds();';
			$queryShape .= 'queryShapeBounds.extend(new google.maps.LatLng('.$previousCriteria["bottomlat"].', '.$previousCriteria["leftlong"].'));';
			$queryShape .= 'queryShapeBounds.extend(new google.maps.LatLng('.$previousCriteria["upperlat"].', '.$previousCriteria["rightlong"].'));';
			$queryShape .= 'map.fitBounds(queryShapeBounds);';
			$queryShape .= 'map.panToBounds(queryShapeBounds);';
		}
		if(isset($previousCriteria["pointlat"]) && $previousCriteria["pointlat"]){
			$radius = (($previousCriteria["radius"]/0.6214)*1000);
			$queryShape = 'var queryCircle = new google.maps.Circle({';
			$queryShape .= 'center: new google.maps.LatLng('.$previousCriteria["pointlat"].', '.$previousCriteria["pointlong"].'),';
			$queryShape .= 'radius: '.$radius.',';
			$queryShape .= $properties;
			$queryShape .= "queryCircle.type = 'circle';";
			$queryShape .= "google.maps.event.addListener(queryCircle, 'click', function() {";
			$queryShape .= 'setSelection(queryCircle);});';
			$queryShape .= "google.maps.event.addListener(queryCircle, 'dragend', function() {";
			$queryShape .= 'setSelection(queryCircle);});';
			$queryShape .= "google.maps.event.addListener(queryCircle, 'radius_changed', function() {";
			$queryShape .= 'setSelection(queryCircle);});';
			$queryShape .= "google.maps.event.addListener(queryCircle, 'center_changed', function() {";
			$queryShape .= 'setSelection(queryCircle);});';
			$queryShape .= 'setSelection(queryCircle);';
			$queryShape .= 'var queryShapeBounds = queryCircle.getBounds();';
			$queryShape .= 'map.fitBounds(queryShapeBounds);';
			$queryShape .= 'map.panToBounds(queryShapeBounds);';
		}
		if(isset($previousCriteria["poly_array"]) && $previousCriteria["poly_array"]){
			$wkt = substr($previousCriteria["poly_array"],9,-2);
			if(substr($wkt,0,1) == '(') $wkt = substr($wkt,1);
			$coordArr = explode(',',$wkt);
			if($coordArr){
				$shapeBounds = 'var queryShapeBounds = new google.maps.LatLngBounds();';
				$queryShape = 'var queryPolygon = new google.maps.Polygon({';
				$points = '';
				foreach($coordArr as $k => $ptStr){
					$ptArr = explode(' ',$ptStr);
					$points .= ',new google.maps.LatLng('.$ptArr[0].', '.$ptArr[1].')';
					$shapeBounds .= 'queryShapeBounds.extend(new google.maps.LatLng('.$ptArr[0].', '.$ptArr[1].'));';
				}
				$queryShape .= 'paths: ['.substr($points,1).'],';
				$queryShape .= $properties;
				$queryShape .= "queryPolygon.type = 'polygon';";
				$queryShape .= "google.maps.event.addListener(queryPolygon, 'click', function() {";
				$queryShape .= 'setSelection(queryPolygon);});';
				$queryShape .= "google.maps.event.addListener(queryPolygon, 'dragend', function() {";
				$queryShape .= 'setSelection(queryPolygon);});';
				$queryShape .= "google.maps.event.addListener(queryPolygon.getPath(), 'insert_at', function() {";
				$queryShape .= 'setSelection(queryPolygon);});';
				$queryShape .= "google.maps.event.addListener(queryPolygon.getPath(), 'remove_at', function() {";
				$queryShape .= 'setSelection(queryPolygon);});';
				$queryShape .= "google.maps.event.addListener(queryPolygon.getPath(), 'set_at', function() {";
				$queryShape .= 'setSelection(queryPolygon);});';
				$queryShape .= 'setSelection(queryPolygon);';
				$queryShape .= $shapeBounds;
				$queryShape .= 'map.fitBounds(queryShapeBounds);';
				$queryShape .= 'map.panToBounds(queryShapeBounds);';
			}
		}
		return $queryShape;
	}

	//KML functions
	public function writeKMLFile($coordArr){
		global $DEFAULT_TITLE, $CLIENT_ROOT, $CHARSET;
		$fileName = $DEFAULT_TITLE;
		if($fileName){
			if(strlen($fileName) > 10) $fileName = substr($fileName,0,10);
			$fileName = str_replace(".","",$fileName);
			$fileName = str_replace(" ","_",$fileName);
		}
		else{
			$fileName = "symbiota";
		}
		$fileName .= time().".kml";
		header ('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header ('Content-type: application/vnd.google-earth.kml+xml');
		header ('Content-Disposition: attachment; filename="'.$fileName.'"');
		echo "<?xml version='1.0' encoding='".$CHARSET."'?>\n";
		echo "<kml xmlns='http://www.opengis.net/kml/2.2'>\n";
		echo "<Document>\n";
		echo "<Folder>\n<name>".$DEFAULT_TITLE." Specimens - ".date('j F Y g:ia')."</name>\n";

		$cnt = 0;
		foreach($coordArr as $sciName => $contentArr){
			$iconStr = $this->googleIconArr[$cnt%44];
			$cnt++;
			unset($contentArr["color"]);

			echo "<Style id='sn_".$iconStr."'>\n";
			echo "<IconStyle><scale>1.1</scale><Icon>";
			echo "<href>http://maps.google.com/mapfiles/kml/".$iconStr.".png</href>";
			echo "</Icon><hotSpot x='20' y='2' xunits='pixels' yunits='pixels'/></IconStyle>\n</Style>\n";
			echo "<Style id='sh_".$iconStr."'>\n";
			echo "<IconStyle><scale>1.3</scale><Icon>";
			echo "<href>http://maps.google.com/mapfiles/kml/".$iconStr.".png</href>";
			echo "</Icon><hotSpot x='20' y='2' xunits='pixels' yunits='pixels'/></IconStyle>\n</Style>\n";
			echo "<StyleMap id='".htmlspecialchars(str_replace(" ","_",$sciName), ENT_QUOTES)."'>\n";
			echo "<Pair><key>normal</key><styleUrl>#sn_".$iconStr."</styleUrl></Pair>";
			echo "<Pair><key>highlight</key><styleUrl>#sh_".$iconStr."</styleUrl></Pair>";
			echo "</StyleMap>\n";
			echo "<Folder><name>".htmlspecialchars($sciName, ENT_QUOTES)."</name>\n";
			foreach($contentArr as $occId => $pointArr){
				echo "<Placemark>\n";
				echo "<name>".htmlspecialchars($pointArr["identifier"], ENT_QUOTES)."</name>\n";
				echo "<ExtendedData>\n";
				echo "<Data name='institutioncode'>".htmlspecialchars($pointArr["institutioncode"], ENT_QUOTES)."</Data>\n";
				echo "<Data name='collectioncode'>".htmlspecialchars($pointArr["collectioncode"], ENT_QUOTES)."</Data>\n";
				echo "<Data name='catalognumber'>".htmlspecialchars($pointArr["catalognumber"], ENT_QUOTES)."</Data>\n";
				echo "<Data name='othercatalognumbers'>".htmlspecialchars($pointArr["othercatalognumbers"], ENT_QUOTES)."</Data>\n";
				echo "<Data name='DataSource'>Data retrieved from ".$DEFAULT_TITLE." Data Portal</Data>\n";
				$url = "http://".$_SERVER["SERVER_NAME"].$CLIENT_ROOT."/collections/individual/index.php?occid=".$occId;
				echo "<Data name='RecordURL'>".$url."</Data>\n";
				echo "</ExtendedData>\n";
				echo "<styleUrl>#".htmlspecialchars(str_replace(" ","_",$sciName), ENT_QUOTES)."</styleUrl>\n";
				echo "<Point><coordinates>".implode(",",array_reverse(explode(",",$pointArr["latLngStr"]))).",0</coordinates></Point>\n";
				echo "</Placemark>\n";
			}
			echo "</Folder>\n";
		}
		echo "</Folder>\n";
		echo "</Document>\n";
		echo "</kml>\n";
	}

	//Garmin functions
	public function getGpxText($seloccids){
		global $DEFAULT_TITLE;
		$seloccids = preg_match('#\[(.*?)\]#', $seloccids, $match);
		$seloccids = $match[1];
		$gpxText = '';
		$gpxText = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>';
		$gpxText .= '<gpx xmlns="http://www.topografix.com/GPX/1/1" version="1.1" creator="mymy">';
		$sql = "";
		$sql = 'SELECT o.occid, o.basisOfRecord, c.institutioncode, o.catalognumber, CONCAT_WS(" ",o.recordedby,o.recordnumber) AS collector, '.
			'o.eventdate, o.family, o.sciname, o.locality, o.DecimalLatitude, o.DecimalLongitude '.
			'FROM omoccurrences o LEFT JOIN omcollections c ON o.collid = c.collid ';
		$sql .= 'WHERE o.occid IN('.$seloccids.') ';
		//echo "<div>".$sql."</div>";
		$result = $this->conn->query($sql);
		while($row = $result->fetch_object()){
			$comment = $row->institutioncode.($row->catalognumber?': '.$row->catalognumber.'. ':'. ');
			$comment .= $row->collector.'. '.$row->eventdate.'. Locality: '.$row->locality.' (occid: '.$row->occid.')';
			$gpxText .= '<wpt lat="'.$row->DecimalLatitude.'" lon="'.$row->DecimalLongitude.'">';
			$gpxText .= '<name>'.$row->sciname.'</name>';
			$gpxText .= '<cmt>'.$comment.'</cmt>';
			$gpxText .= '<sym>Waypoint</sym>';
			$gpxText .= '</wpt>';
		}
		$gpxText .= '</gpx>';

		return $gpxText;
	}

	//Dataset functions
	public function getOccurrences($datasetId){
		$retArr = array();
		if($datasetId){
			$sql = 'SELECT o.occid, o.catalognumber, CONCAT_WS(" ",o.recordedby,o.recordnumber) AS collector, o.eventdate, '.
				'o.family, o.sciname, CONCAT_WS("; ",o.country, o.stateProvince, o.county) AS locality, o.DecimalLatitude, o.DecimalLongitude '.
				'FROM omoccurrences o LEFT JOIN omoccurdatasetlink dl ON o.occid = dl.occid '.
				'WHERE dl.datasetid = '.$datasetId.' '.
				'ORDER BY o.sciname ';
			$rs = $this->conn->query($sql);
			while($r = $rs->fetch_object()){
				$retArr[$r->occid]['occid'] = $r->occid;
				$retArr[$r->occid]['sciname'] = $r->sciname;
				$retArr[$r->occid]['catnum'] = $r->catalognumber;
				$retArr[$r->occid]['coll'] = $r->collector;
				$retArr[$r->occid]['eventdate'] = $r->eventdate;
				$retArr[$r->occid]['occid'] = $r->occid;
				$retArr[$r->occid]['lat'] = $r->DecimalLatitude;
				$retArr[$r->occid]['long'] = $r->DecimalLongitude;
			}
			$rs->free();
		}
		if(count($retArr)>1){
			return $retArr;
		}
		else{
			return;
		}
	}

	public function getPersonalRecordsets($uid){
		$retArr = Array();
		$sql = "";
		//Get datasets owned by user
		$sql = 'SELECT datasetid, name '.
			'FROM omoccurdatasets '.
			'WHERE (uid = '.$uid.') '.
			'ORDER BY name';
		$rs = $this->conn->query($sql);
		while($r = $rs->fetch_object()){
			$retArr[$r->datasetid]['datasetid'] = $r->datasetid;
			$retArr[$r->datasetid]['name'] = $r->name;
			$retArr[$r->datasetid]['role'] = "DatasetAdmin";
		}
		$sql2 = 'SELECT d.datasetid, d.name, r.role '.
			'FROM omoccurdatasets d LEFT JOIN userroles r ON d.datasetid = r.tablepk '.
			'WHERE (r.uid = '.$uid.') AND (r.role IN("DatasetAdmin","DatasetEditor","DatasetReader")) '.
			'ORDER BY sortsequence,name';
		$rs = $this->conn->query($sql2);
		while($r = $rs->fetch_object()){
			$retArr[$r->datasetid]['datasetid'] = $r->datasetid;
			$retArr[$r->datasetid]['name'] = $r->name;
			$retArr[$r->datasetid]['role'] = $r->role;
		}
		$rs->free();
		return $retArr;
	}

	//Misc functions
	public function getObservationIds(){
		$retVar = array();
		$sql = 'SELECT collid FROM omcollections WHERE CollType IN("Observations","General Observations") ';
		$rs = $this->conn->query($sql);
		while($r = $rs->fetch_object()){
			$retVar[] = $r->collid;
		}
		$rs->free();
		return $retVar;
	}

	public function getMysqlVersion(){
		$version = array();
		$output = '';
		if(mysqli_get_server_info($this->conn)){
			$output = mysqli_get_server_info($this->conn);
		}
		else{
			$output = shell_exec('mysql -V');
		}
		if($output){
			if(strpos($output,'MariaDB') !== false){
				$version["db"] = 'MariaDB';
			}
			else{
				$version["db"] = 'mysql';
				preg_match('@[0-9]+\.[0-9]+\.[0-9]+@',$output,$ver);
				$version["ver"] = $ver[0];
			}
		}
		return $version;
	}

	//Misc support functions
	private function htmlEntities($string){
		return htmlspecialchars($string, ENT_XML1 | ENT_QUOTES, 'UTF-8');
	}
}
?>