<?php


function delete_temporary_collection($dbname)	{
	$connection = new Mongo();
	$mongodb = $connection->$dbname;
	$collections = $mongodb->listCollections();
	
	$pattern1 = "/^tmp.mr.mapreduce_([0-9a-z_]*)$/";
	$pattern2 = "/^mr.([a-z_]*).([0-9a-z_.]*)$/";	//used by MongoDB 1.2.x or earlier 
	
	
	/**
	 * this is for testing regex pattern
	$arr = Array('mr.1260.551686_355_inc', 'tmp.mr.mapreduce_1260551686_355_inc', 'tmp.mr.mapreduce_1260551686_355', 'tmp.mr.mapreduce_1260551686_355_INC', 'mp.mr.mapreduce_1260551686_355_inc', 'tmp.mr.mapreduce1260551686_355_inc');
	
	foreach ($arr as $a)	{
		$match1 = preg_match($pattern1, $a);
		$match2 = preg_match($pattern2, $a);
		if ($match1 === 1 || $match2 === 1)   {
			echo $a."\n";
		}
		else	{
			echo "Not matched for $a\n";
		}
	}
	**/
	
	$total_count = 0;
	$removed_count = 0;
	
	foreach ($collections as $collection) {
		$name = $collection->getName();	
		$match = preg_match($pattern, $name);
		if ($match === 1)	{		
			$response = $collection->drop();
			if ($response['ok'] == 1)	{
				// echo "removing $name... \n";
				$removed_count++;
			}
			$total_count++;					
		}	
	}
	echo "******************\nTOTAL TEMPORARY COLLECTIONS REMOVED = {$removed_count} out of {$total_count}\n*********************\n";
}

delete_temporary_collection('test_collections');