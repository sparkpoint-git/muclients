<?php

/** Prepare args: current page number, order by, LIMIT, OFFSET...
	$default_order_by contains the default column for ORDER BY
	$search_key is the column name in which we will search if the user choose the first radio in the form
	$search_value is the column name in which we will search if the user choose the second radio in the form
	args by WP: paged, order_by, order, 
	args be aDBc: per_page, s, in
 */
function aDBc_get_search_sql_arg($search_in_key, $search_in_value){

		// Prepare LIKE sql clause
		$search_like = "";
		if(!empty($_GET['s']) && trim($_GET['s']) != ""){
			$search = esc_sql(sanitize_text_field($_GET['s']));
			$in = $search_in_key;
			if(!empty($_GET['in'])){
				$in = ($_GET['in'] == "key") ? $search_in_key : $search_in_value;
			}
			$search_like = " AND $in LIKE '%{$search}%'";
		}

		return $search_like;
}

/***********************************************************************************
* This function filters the array containing results according to users args
***********************************************************************************/
function aDBc_filter_results_in_all_items_array(&$aDBc_all_items, $aDBc_tables_name_to_optimize, $aDBc_tables_name_to_repair){

	if(function_exists('is_multisite') && is_multisite()){

		// Filter according to sites
		if(!empty($_GET['site'])){
			foreach($aDBc_all_items as $item_name => $item_info){
				foreach($item_info['sites'] as $site_id => $site_item_info){
					if($site_id != $_GET['site']){
						unset($aDBc_all_items[$item_name]['sites'][$site_id]);
					}
				}
			}
		}

		// Filter according to search
		if(!empty($_GET['s']) && trim($_GET['s']) != ""){
			$search = esc_sql(sanitize_text_field($_GET['s']));
			foreach($aDBc_all_items as $item_name => $item_info){
				foreach($item_info['sites'] as $site_id => $site_item_info){
					$table_prefix_if_exists = empty($site_item_info['prefix']) ? "" : $site_item_info['prefix'];
					if(strpos($table_prefix_if_exists . $item_name, $search) === false){
						unset($aDBc_all_items[$item_name]['sites'][$site_id]);
					}
				}
			}
		}

		// Filter according to tables types (to optimize, to repair...)
		if(!empty($_GET['t_type']) && $_GET['t_type'] != "all"){
			$type = esc_sql($_GET['t_type']);
			if($type == 'optimize'){
				$array_names = $aDBc_tables_name_to_optimize;
			}else{
				$array_names = $aDBc_tables_name_to_repair;
			}
			foreach($aDBc_all_items as $item_name => $item_info){
				foreach($item_info['sites'] as $site_id => $site_item_info){
					if(!in_array($site_item_info['prefix'] . $item_name, $array_names)){
						unset($aDBc_all_items[$item_name]['sites'][$site_id]);
					}
				}
			}
		}

		// Filter according to autoload
		if(!empty($_GET['autoload']) && $_GET['autoload'] != "all"){
			$autoload_param = esc_sql($_GET['autoload']);
			foreach($aDBc_all_items as $item_name => $item_info){
				foreach($item_info['sites'] as $site_id => $site_item_info){
					if($site_item_info['autoload'] != $autoload_param){
						unset($aDBc_all_items[$item_name]['sites'][$site_id]);
					}
				}
			}
		}

		// Filter according to belongs_to
		if(!empty($_GET['belongs_to']) && $_GET['belongs_to'] != "all"){
			$belongs_to_param = esc_sql($_GET['belongs_to']);
			$names_to_delete = array();
			foreach($aDBc_all_items as $item_name => $item_info){
				$belongs_to_value = explode("(", $item_info['belongs_to'], 2);
				$belongs_to_value = trim($belongs_to_value[0]);
				$belongs_to_value = str_replace(" ", "-", $belongs_to_value);
				if($belongs_to_value != $belongs_to_param){
					array_push($names_to_delete, $item_name);
				}
			}
			// Loop over the names to delete and delete them for the array
			foreach($names_to_delete as $name){
				unset($aDBc_all_items[$name]);
			}
		}
		

	}else{

		// Prepare an array containing names of items to delete
		$names_to_delete = array();

		// Filter according to search parameter
		$filter_on_search = !empty($_GET['s']) && trim($_GET['s']) != "";
		if($filter_on_search){
			$search = esc_sql(sanitize_text_field($_GET['s']));
		}

		// Filter according to tables types (to optimize, to repair...)
		$filter_on_t_type = !empty($_GET['t_type']) && $_GET['t_type'] != "all";
		if($filter_on_t_type){
			$type = esc_sql($_GET['t_type']);
			if($type == "optimize"){
				$array_names = $aDBc_tables_name_to_optimize;
			}else{
				$array_names = $aDBc_tables_name_to_repair;
			}			
		}

		// Filter according to autoload
		$filter_on_autoload = !empty($_GET['autoload']) && $_GET['autoload'] != "all";
		if($filter_on_autoload){
			$autoload_param = esc_sql($_GET['autoload']);
		}
		
		// Filter according to belongs_to
		$filter_on_belongs_to = !empty($_GET['belongs_to']) && $_GET['belongs_to'] != "all";
		if($filter_on_belongs_to){
			$belongs_to_param = esc_sql($_GET['belongs_to']);
		}

		foreach($aDBc_all_items as $item_name => $item_info){

			if($filter_on_search){
				if(@strpos($item_info['sites'][1]['prefix'] . $item_name, $search) === false){
					array_push($names_to_delete, $item_name);
				}
			}

			if($filter_on_t_type){
				if(!in_array($item_info['sites'][1]['prefix'] . $item_name, $array_names)){
					array_push($names_to_delete, $item_name);
				}
			}
			
			if($filter_on_autoload){
				if($item_info['sites'][1]['autoload'] != $autoload_param){
					array_push($names_to_delete, $item_name);
				}
			}			

			if($filter_on_belongs_to){
				$belongs_to_value = explode("(", $item_info['belongs_to'], 2);
				$belongs_to_value = trim($belongs_to_value[0]);
				$belongs_to_value = str_replace(" ", "-", $belongs_to_value);
				if($belongs_to_value != $belongs_to_param){
					array_push($names_to_delete, $item_name);
				}
			}			

		}

		// Loop over the names to delete and delete them for the array
		foreach($names_to_delete as $name){
			unset($aDBc_all_items[$name]);
		}		

	}

}

function aDBc_get_progress_bar_width(){

	//if(isset($_REQUEST)){

		$items_type = $_REQUEST['aDBc_item_type'];

		// If files does not exist, create them to prevent errors of reading
		$path_total_items 	= ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/total_items_" . $items_type . ".txt";
		$path_progress 		= ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/progress_items_" . $items_type . ".txt";
		$path_file_timeout 	= ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/timeout_" . $items_type . ".txt";

		$progress = 0;
		$total_items = 0; // To prevent division per 0 in ajax call
		$timeout = false;
		$stop = false;
		
		if(!isset($_SESSION)) {
			session_start();
		}
		if(!empty($_SESSION['aDBc_progress'])){
			$progress = $_SESSION['aDBc_progress'];
		}

		//if(file_exists($path_total_items) && file_exists($path_progress)){

		if(file_exists($path_total_items)){
			
			$total_items 	= trim(file_get_contents($path_total_items));
			//$progress		= trim(file_get_contents($path_progress));
			$timeout 		= file_exists($path_file_timeout);

			if($timeout || $progress >= $total_items){
				$stop = true;
				@unlink($path_file_timeout);
			}

		}

		$status = array(
			'aDBc_progress' 	=> $progress,
			'aDBc_total_items' 	=> $total_items,
			'aDBc_timeout' 		=> $timeout,
			'aDBc_stop' 		=> $stop
		 );	

		echo json_encode($status);

	//}

	die();
}

/************************************************************************************
* Searches for any item name in the "$items_to_search_for" in all files of WordPress
************************************************************************************/
function aDBc_new_run_search_for_items(){

	// parameters of this function : $aDBc_core_items, &$aDBc_success_message, $items_type

	// Since scan results will be saved in files, test if 'aDBc_uploads' exists. If no, show error msg and exit
	if(!file_exists(ADBC_UPLOAD_DIR_PATH_TO_ADBC)){
		update_option("aDBc_permission_adbc_folder_needed", "yes");
		return;
	}

    // The $_REQUEST contains all the data sent via ajax
    if(isset($_REQUEST)){

		$items_type = $_REQUEST['aDBc_item_type'];

		// Prevent executing this function multiple times by ajax
		update_option("aDBc_temp_still_searching_" . $items_type, "yes", "no");

		/**********************************************************************************************************************
		* Prepare all paths to files that will be used during the process
		***********************************************************************************************************************/
		$path_file_categorization 	= ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/" . $items_type . ".txt";
		$path_file_to_categorize 	= ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/" . $items_type . "_to_categorize.txt";
		$path_file_timeout			= ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/timeout_" . $items_type . ".txt";
		$path_file_total_items 		= ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/total_items_" . $items_type .".txt";
		$path_file_all_php_files	= ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/all_files_paths.txt";
		$path_file_progress_items	= ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/progress_items_" . $items_type . ".txt";
		$path_file_maybe_scores		= ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/maybe_scores_" . $items_type . ".txt";
		
		// If the user refresh tha page before the ajax call ends, the ajax call will continue executing in backgound and the register_shutdown function will be called after timeout // and create the timeout file. We should delete that file when runing a new scan 
		@unlink($path_file_timeout);

		global $items_to_search_for;

		switch($items_type){
			case 'tasks' :
				$aDBc_core_items 		= aDBc_get_core_tasks();
				$items_to_search_for 	= aDBc_get_all_scheduled_tasks();
				break;
			case 'options' :
				$aDBc_core_items 		= aDBc_get_core_options();
				$items_to_search_for 	= aDBc_get_all_options();
				break;
			case 'tables' :
				$aDBc_core_items 		= aDBc_get_core_tables();
				$items_to_search_for 	= aDBc_get_all_tables();
				break;
		}

		// This global variable it used by the shutdown function when timeout. We send the item type to that function via a global variable
		global $item_type_for_shutdown;
		$item_type_for_shutdown = $items_type;

		// Stores the current line that should be processed in "to_categorize.txt". Default value is 1, unless a timeout has occured
		global $aDBc_item_line;
		$item_line 			= get_option("aDBc_temp_last_item_line_" . $items_type);
		$aDBc_item_line 	= empty($item_line)? 1 : $item_line;

		// Stores the current line that have been reached in "all_files_paths.txt". Default value is 1, unless a timeout has occured
		global $aDBc_file_line;
		$file_line 			= get_option("aDBc_temp_last_file_line_" . $items_type);
		$aDBc_file_line 	= empty($file_line)? 1 : $file_line;

		// Stores the iteration number: either 1 or 2. Default is one, unless a timeout has occured, it maybe 2 at that time
		global $aDBc_iteration;
		// Test if we should load and override the iteration if a timeout has been occured
		$iteration 			= get_option("aDBc_temp_last_iteration_" . $items_type);
		$aDBc_iteration 	= empty($iteration)? 1 : $iteration;

		// Stores if the search has finished or not. When this function is called, always set this to "no". It will be set to "yes" at the end.
		global $aDBc_search_has_finished;
		$aDBc_search_has_finished = "no";

		// Count total items in memory
		$total_items_in_memory 	= count($items_to_search_for);

		// Create an array that will hundle already categorized items
		$items_already_categorized = array();

		// Save total files found. This global variable is incremented in aDBc_refresh_and_create_php_files_paths function accordinly
		global $aDBc_total_files;
		$aDBc_total_files = 0;

		/***********************************************************************************************************************
		* This section prepares files to run a new search. If aDBc_temp_last_iteration_ already exists, this means that we have
		* started a search and not finish it yet, just skip this section then
		***********************************************************************************************************************/
		if(empty($iteration)){

			// Delete old files
			@unlink($path_file_categorization);
			@unlink($path_file_to_categorize);
			@unlink($path_file_total_items);
			@unlink($path_file_all_php_files);
			@unlink($path_file_progress_items);
			@unlink($path_file_maybe_scores);

			// Delete option of success from database
			delete_option("aDBc_last_search_ok_" . $items_type);

			// Refresh "all_files_paths.txt" containing all wordpress php files paths
			aDBc_refresh_and_create_php_files_paths();

			// To calculate progress of research, we will base on total files x2. Because we have 2 iterations in which we go through all files.
			$total_items_file 		= fopen($path_file_total_items, "w");
			fwrite($total_items_file, $aDBc_total_files * 2);
			fclose($total_items_file);
			$aDBc_total_files = $aDBc_total_files * 2;

			// Open the file in which we will save searching results as and when. If it does not exist, it will be created
			$myfile_categorization = fopen($path_file_categorization, "a");

			// Create the file named $items_type."to_categorize.txt" containing all $items_type to categorize while searching for orphans. Then fill it
			$myfile_to_categorize = fopen($path_file_to_categorize, "a");

			foreach($items_to_search_for as $aDBc_item => $aDBc_info){
				// Add all items to $myfile_to_categorize
				fwrite($myfile_to_categorize, $aDBc_item . "\n");
				// If the item belong to core, categorize it directly
				if(in_array($aDBc_item, $aDBc_core_items)){
					fwrite($myfile_categorization, str_replace(":", "+=+", $aDBc_item) . ":w:w" . "\n");
					array_push($items_already_categorized, $aDBc_item);
					// We fill belongs to to prevent processing this item later since it is already categorized
					$items_to_search_for[$aDBc_item]['belongs_to'] = "ok";
				}
			}

			fclose($myfile_categorization);
			fclose($myfile_to_categorize);

		}else{

			/**********************************************************************************************************************
			* If we continue after timeout, we will do some adjustments
			***********************************************************************************************************************/			

			$aDBc_total_files = trim(file_get_contents($path_file_total_items));

			// There is a case when a user can have timeout, and after that timeout, a plugin added some new items to database, we should then add those new items in file
			$total_items_in_file = 0;
			$myfile_to_categorize = fopen($path_file_to_categorize, "r");
			while(($item = fgets($myfile_to_categorize)) !== false){
				$total_items_in_file++;
			}
			fclose($myfile_to_categorize);

			// If items in memory are big than in files, this means that some new items have been added, we should add them to the end of the file
			if($total_items_in_memory > $total_items_in_file){
				$items_list_in_file = array();
				$myfile_to_categorize = fopen($path_file_to_categorize, "r");
				while(($item = fgets($myfile_to_categorize)) !== false){
					array_push($items_list_in_file, trim($item));
				}
				fclose($myfile_to_categorize);

				$myfile_to_categorize = fopen($path_file_to_categorize, "a");
				foreach($items_to_search_for as $aDBc_item => $aDBc_info){
					if(!in_array($aDBc_item, $items_list_in_file)){
						fwrite($myfile_to_categorize, $aDBc_item . "\n");
					}
				}
				fclose($myfile_to_categorize);
			}

			// After adjusting new items in file. We will delete from $items_to_search_for items that have been already categorized to save time in iteration 1
			$myfile_categorization = fopen($path_file_categorization, "r");
			while(($item = fgets($myfile_categorization)) !== false){
				$item_name = explode(":", trim($item), 2);
				$item_name = str_replace("+=+", ":", $item_name[0]);
				if(array_key_exists($item_name, $items_to_search_for)){
					$items_to_search_for[$item_name]['belongs_to'] = "ok";
				}
				array_push($items_already_categorized, $item_name);
			}
			fclose($myfile_categorization);

		}

		/**********************************************************************************************************************
		* 
		* We proceed to iteration through all files, items....
		*
		***********************************************************************************************************************/	

		// Prepare an array containing all items we will iterate through
		$myfile_to_categorize = fopen($path_file_to_categorize, "r");
		$to_categorize_array = array();
		while(($item = fgets($myfile_to_categorize)) !== false){
			array_push($to_categorize_array, trim($item));
		}
		fclose($myfile_to_categorize);

		// Prepare an array containing all files we will iterate through
		$all_files_paths = fopen($path_file_all_php_files, "r");
		$all_files_array = array();
		while(($file_path = fgets($all_files_paths)) !== false){
			array_push($all_files_array, trim($file_path));
		}
		fclose($all_files_paths);

		// Get the number of items processed until now
		$processed_items = count($items_already_categorized);

		// Open the file in which we will save searching results as and when
		$myfile_categorization = fopen($path_file_categorization, "a");

		// Iteration 1: Search in all files for exact match for all items
		if($aDBc_iteration == 1){

			$file_line_index = 1;

			foreach($all_files_array as $file_path){

				// We write the progress for ajax
				/*$progress_items_file = fopen($path_file_progress_items, "w");
				fwrite($progress_items_file, $file_line_index);
				fclose($progress_items_file);*/
				
				session_start();
				$_SESSION['aDBc_progress'] = $file_line_index;
				session_write_close();

				// Skip until we found the last file before timeout
				if($file_line_index < $aDBc_file_line){
					$file_line_index++;
					continue;
				}

				$aDBc_file_content = file_get_contents($file_path);

				$item_line_index = 1;

				foreach($to_categorize_array as $item_name){

					// Skip until we found the last item before timeout
					if($item_line_index < $aDBc_item_line){
						$item_line_index++;
						continue;
					}

					// Before scaning the item, we test if the item has not been already categorized 
					if(array_key_exists($item_name, $items_to_search_for) && $items_to_search_for[$item_name]['belongs_to'] != "ok"){
					//if(!in_array($item_name, $items_already_categorized)){
						// If exact match found
						if(strpos($aDBc_file_content, $item_name) !== false){
							// We update data, identify plugin or theme names,....
							$owner_name_type = aDBc_get_owner_name_from_path($item_name, $file_path);
							fwrite($myfile_categorization, str_replace(":", "+=+", $item_name) . ":" . $owner_name_type[0] . ":" . $owner_name_type[1] . "\n");
							//array_push($items_already_categorized, $item_name);
							$processed_items++;
							// Put ok in belongs_to
							$items_to_search_for[$item_name]['belongs_to'] = "ok";

							// If we have categorized all items, break from all loops (2 loops)
							if($processed_items >= $total_items_in_memory){
								break 2;
							}
						}
					}

					$aDBc_item_line++;
					$item_line_index++;

				}

				$aDBc_item_line = 1;

				$file_line_index++;
				$aDBc_file_line++;

			}

			// If we have not categorized all items in iteration 1, we should execute iteration 2	
			if($processed_items < $total_items_in_memory){
				$aDBc_iteration = 2;
				$aDBc_file_line = 1;
				$aDBc_item_line = 1;
			}
		}

		// Iteration 2: Search in all files for partial match for items that are not categorized in iteration 1
		if($aDBc_iteration == 2){

			// If we are in iteration 2, we start by verifying if maybe_scores file exists, if so, load its data to $items_to_search_for
			if(file_exists($path_file_maybe_scores)){
				$myfile_maybe_scores = fopen($path_file_maybe_scores, "r");
				while(($item = fgets($myfile_maybe_scores)) !== false){
					$info = explode(":", trim($item), 2);
					$name = str_replace("+=+", ":", $info[0]);
					if(array_key_exists($name, $items_to_search_for) ){
						$items_to_search_for[$name]['maybe_belongs_to'] = $info[1];
					}
				}
				fclose($myfile_maybe_scores);
				// Once we finish, we delete this file
				@unlink($path_file_maybe_scores);
			}

			$file_line_index = 1;
			$half_files = $aDBc_total_files / 2;
			foreach($all_files_array as $file_path){

				// We write the progress for ajax
				/*$progress_items_file = fopen($path_file_progress_items, "w");
				fwrite($progress_items_file, $half_files + $file_line_index);
				fclose($progress_items_file);*/

				session_start();
				$_SESSION['aDBc_progress'] = $half_files + $file_line_index;
				session_write_close();

				// Skip until we found the last file before timeout
				if($file_line_index < $aDBc_file_line){
					$file_line_index++;
					continue;
				}
				$aDBc_file_content = strtolower(file_get_contents($file_path));
				$item_line_index = 1;
				foreach($to_categorize_array as $item_name){
					// Skip until we found the last item before timeout
					if($item_line_index < $aDBc_item_line){
						$item_line_index++;
						continue;
					}
					// Before scaning the item, we test if the item has not been already categorized
					if(array_key_exists($item_name, $items_to_search_for) && $items_to_search_for[$item_name]['belongs_to'] != "ok"){
						// Find partial match. If found, add it directly to maybe_belongs_to in $items_to_search_for
						aDBc_search_for_partial_match($item_name, $aDBc_file_content, $file_path, $items_to_search_for);
					}

					$aDBc_item_line++;
					$item_line_index++;
				}
				$aDBc_item_line = 1;
				$file_line_index++;
				$aDBc_file_line++;
			}

			// After finishing all partial matches. Write results to file
			foreach($items_to_search_for as $aDBc_item => $aDBc_info){
				
				if($aDBc_info['belongs_to'] != "ok"){

					$aDBc_maybe_belongs_to_parts = explode("/", $aDBc_info['maybe_belongs_to']);

					// If the part1 is not empty, we will use it, else use the part 2
					if(!empty($aDBc_maybe_belongs_to_parts[0])){

						$aDBc_maybe_belongs_to_info = explode("|", $aDBc_maybe_belongs_to_parts[0]);
						$belongs_to = $aDBc_maybe_belongs_to_info[0] == "w" ? "" : $aDBc_maybe_belongs_to_info[0];
						// If $aDBc_maybe_belongs_to_info[2] equals to 100%, then delete pourcentage
						if($aDBc_maybe_belongs_to_info[2] != "100"){
							$belongs_to .= " (".$aDBc_maybe_belongs_to_info[2]."%)";
						}
						$type = $aDBc_maybe_belongs_to_info[1];	

					}else if(!empty($aDBc_maybe_belongs_to_parts[1])){

						$aDBc_maybe_belongs_to_info = explode("|", $aDBc_maybe_belongs_to_parts[1]);
						$belongs_to = $aDBc_maybe_belongs_to_info[0] == "w" ? "" : $aDBc_maybe_belongs_to_info[0];
						// If $aDBc_maybe_belongs_to_info[2] equals to 100%, then delete pourcentage
						if($aDBc_maybe_belongs_to_info[2] != "100"){
							$belongs_to .= " (".$aDBc_maybe_belongs_to_info[2]."%)";
						}
						$type = $aDBc_maybe_belongs_to_info[1];

					}else{

						// As final step, make all items to orphan if they have an empty "belong_to"
						$belongs_to = "o";
						$type = "o";
					}

					$aDBc_items_status = str_replace(":", "+=+", $aDBc_item) . ":" . $belongs_to . ":" . $type;
					fwrite($myfile_categorization, $aDBc_items_status . "\n");

				}

			}
		}

		$aDBc_search_has_finished = "yes";

		// After the search has been finished, close files and delete the all temp options that have been added to DB
		// First, we create an option in database to show a message that the search has finished and let users opt for double check against our server
		update_option("aDBc_last_search_ok_" . $items_type, "1", "no");

		fclose($myfile_categorization);

		delete_option("aDBc_temp_last_item_line_" . $items_type);
		delete_option("aDBc_temp_last_file_line_" . $items_type);
		delete_option("aDBc_temp_last_iteration_" . $items_type);
		delete_option("aDBc_temp_still_searching_" . $items_type);

		// Always die in functions echoing ajax content
        die();

	}
}

/**************************************************************************
* This function is executed if timeout is reached 
***************************************************************************/
function aDBc_shutdown_due_to_timeout(){

	global $aDBc_search_has_finished;

	// Stores the item type we are dealing with: tables, options or tasks
	global $item_type_for_shutdown;

	if($aDBc_search_has_finished == "no"){

		// We create a file of timeout. If this file exists, this means that a timeout has occured
		$file_timeout = fopen(ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/timeout_" . $item_type_for_shutdown . ".txt", "w");
		fclose($file_timeout);

		// Stores the last line that have been processed
		global $aDBc_item_line;
		// Stores the last line that have been reached
		global $aDBc_file_line;		
		// Stores the iteration number: either 1 or 2
		global $aDBc_iteration;

		$last_item_line 	= "aDBc_temp_last_item_line_" . $item_type_for_shutdown;
		$last_file_line 	= "aDBc_temp_last_file_line_" . $item_type_for_shutdown;
		$last_iteration		= "aDBc_temp_last_iteration_" . $item_type_for_shutdown;

		update_option($last_item_line, $aDBc_item_line, "no");
		update_option($last_file_line, $aDBc_file_line, "no");
		update_option($last_iteration, $aDBc_iteration, "no");

		// If timeout or end script, delete still searching
		delete_option("aDBc_temp_still_searching_" . $item_type_for_shutdown);

		// If we are in iteration 2, We create a file containing current maybe scores

		if($aDBc_iteration == 2){

			// Delete the old file if any
			@unlink(ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/maybe_scores_" . $item_type_for_shutdown . ".txt");

			global $items_to_search_for;

			$myfile_maybe_scores = fopen(ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/maybe_scores_" . $item_type_for_shutdown . ".txt", "a");

			foreach($items_to_search_for as $aDBc_item => $aDBc_info){
				if($aDBc_info['belongs_to'] != "ok" && !empty($aDBc_info['maybe_belongs_to'])){
					fwrite($myfile_maybe_scores, str_replace(":", "+=+", $aDBc_item) . ":" . $aDBc_info['maybe_belongs_to'] . "\n");
				}
			}
		}
	}
}

/************************************************************************************************
* This fuction tries to find any partial match of the item_name in the current file then returns:
************************************************************************************************/
function aDBc_search_for_partial_match($aDBc_item_name, $aDBc_file_content, $file_path, &$items_to_search_for){

	// call the last best maybe score
	$aDBc_maybe_score = empty($items_to_search_for[$aDBc_item_name]['maybe_belongs_to']) ? "/" : $items_to_search_for[$aDBc_item_name]['maybe_belongs_to'];

	$aDBc_maybe_belongs_to_parts = explode("/", $aDBc_maybe_score);

	// In itereation 2, change name to lowercase
	$item_name = strtolower($aDBc_item_name);

	$aDBc_item_name_len = strlen($item_name);
	$aDBc_is_new_score_found = 0;

	$aDBc_percent1 = 35;
	$aDBc_item_part1 = substr($item_name, 0, (($aDBc_percent1 * $aDBc_item_name_len) / 100));
	$aDBc_percent2 = 75;
	$aDBc_item_part2 = substr($item_name, -(($aDBc_percent2 * $aDBc_item_name_len) / 100));

	// If aDBc_item_part1 appears in the file content
	if(strpos($aDBc_file_content, $aDBc_item_part1) !== false){
		
		$aDBc_maybe_belongs_to_info_part1 = explode("|", $aDBc_maybe_belongs_to_parts[0]);
		$aDBc_maybe_best_score_found = empty($aDBc_maybe_belongs_to_info_part1[2]) ? $aDBc_percent1 : $aDBc_maybe_belongs_to_info_part1[2];
		// Search for all combinations starting from the beginning of the item name
		for ($i = $aDBc_item_name_len; $i > 1; $i--) {
			$aDBc_substring = substr($item_name, 0, $i);
			$aDBc_percent = (strlen($aDBc_substring) * 100) / $aDBc_item_name_len;
			if($aDBc_percent > $aDBc_maybe_best_score_found){
				if(strpos($aDBc_file_content, $aDBc_substring) !== false){
					// Bingo, we have find a percent %
					$aDBc_maybe_best_score_found = round($aDBc_percent, 2);
					$aDBc_is_new_score_found = 1;
					// Break after the first item found, since it is the longest
					break;
				}
			}else{
				break;
			}
		}

	}

	// If aDBc_item_part2 appears in the file content
	if(strpos($aDBc_file_content, $aDBc_item_part2) !== false){

		$aDBc_maybe_belongs_to_info_part2 = explode("|", $aDBc_maybe_belongs_to_parts[1]);
		$aDBc_maybe_best_score_found = empty($aDBc_maybe_belongs_to_info_part2[2]) ? $aDBc_percent2 : $aDBc_maybe_belongs_to_info_part2[2];
		// Search for all combinations starting from the end of the item name
		for ($i = 0; $i < $aDBc_item_name_len; $i++) {
			$aDBc_substring = substr($item_name, $i);
			$aDBc_percent = (strlen($aDBc_substring) * 100) / $aDBc_item_name_len;
			if($aDBc_percent > $aDBc_maybe_best_score_found){
				if(strpos($aDBc_file_content, $aDBc_substring) !== false){
					// Bingo, we have find a percent %
					$aDBc_maybe_best_score_found = round($aDBc_percent, 2);
					$aDBc_is_new_score_found = 2;
					// Break after the first item found, since it is the longest
					break;
				}
			}else{
				break;
			}
		}

	}

	// Test is new score was found in order to update data
	if($aDBc_is_new_score_found){
		$aDBc_type_detected = 0;
		// Is a plugin?
		if(strpos($file_path, ADBC_WP_PLUGINS_DIR_PATH) !== false){
			$aDBc_path = str_replace(ADBC_WP_PLUGINS_DIR_PATH."/", "", $file_path);
			$plugin_name = explode("/", $aDBc_path, 2);
			// If the new score is >= 100%, fill belongs_to directly instead of maybe_belongs_to to win time
			$aDBc_new_part = $plugin_name[0] . "|p|" . $aDBc_maybe_best_score_found;
			if($aDBc_is_new_score_found == "1"){
				$items_to_search_for[$aDBc_item_name]['maybe_belongs_to'] = $aDBc_new_part . "/" . $aDBc_maybe_belongs_to_parts[1];
			}else{
				$items_to_search_for[$aDBc_item_name]['maybe_belongs_to'] = $aDBc_maybe_belongs_to_parts[0] . "/" . $aDBc_new_part;
			}
			$aDBc_type_detected = 1;
		}
		// If not a plugin, then is a theme?
		if(!$aDBc_type_detected){

			// Prepare WP Themes directories paths (useful to detect if an item belongs to a theme and detect the theme name)
			global $wp_theme_directories;
			$aDBc_themes_paths_array = array();
			foreach($wp_theme_directories as $aDBc_theme_path){
				array_push($aDBc_themes_paths_array, str_replace('\\' ,'/', $aDBc_theme_path));
			}

			foreach($aDBc_themes_paths_array as $aDBc_theme_path){
				if(strpos($file_path, $aDBc_theme_path) !== false){
					$aDBc_path = str_replace($aDBc_theme_path."/", "", $file_path);
					$theme_name = explode("/", $aDBc_path, 2);
					// If the new score is >= 100%, fill belongs_to directly instead of maybe_belongs_to to win time
					$aDBc_new_part = $theme_name[0] . "|t|" . $aDBc_maybe_best_score_found;
					if($aDBc_is_new_score_found == "1"){
						$items_to_search_for[$aDBc_item_name]['maybe_belongs_to'] = $aDBc_new_part . "/" . $aDBc_maybe_belongs_to_parts[1];
					}else{
						$items_to_search_for[$aDBc_item_name]['maybe_belongs_to'] = $aDBc_maybe_belongs_to_parts[0] . "/" . $aDBc_new_part;
					}
					$aDBc_type_detected = 1;
					break;
				}
			}
		}
		// If not a plugin and not a theme, then affect it to WP?
		if(!$aDBc_type_detected){
			// If the new score is >= 100%, fill belongs_to directly instead of maybe_belongs_to to win time
			$aDBc_new_part = "w|w|" . $aDBc_maybe_best_score_found;
			if($aDBc_is_new_score_found == "1"){
				$items_to_search_for[$aDBc_item_name]['maybe_belongs_to'] = $aDBc_new_part . "/" . $aDBc_maybe_belongs_to_parts[1];
			}else{
				$items_to_search_for[$aDBc_item_name]['maybe_belongs_to'] = $aDBc_maybe_belongs_to_parts[0] . "/" . $aDBc_new_part;
			}
		}
	}
}

/**************************************************************************************************************
* Return an array containing the name and the type of the owner of the item in parameter based on the file path
**************************************************************************************************************/
function aDBc_get_owner_name_from_path($item_name, $full_path){

	$owner_name_type = array();

	// Is a plugin?
	if(strpos($full_path, ADBC_WP_PLUGINS_DIR_PATH) !== false){
		$aDBc_path = str_replace(ADBC_WP_PLUGINS_DIR_PATH."/", "", $full_path);
		$plugin_name = explode("/", $aDBc_path, 2);
		$owner_name_type[0] = $plugin_name[0];
		$owner_name_type[1] = "p";
		return $owner_name_type;
	}

	// If not a plugin, then is a theme?
	// Prepare WP Themes directories paths (useful to detect if an item belongs to a theme and detect the theme name)
	global $wp_theme_directories;
	$aDBc_themes_paths_array = array();
	foreach($wp_theme_directories as $aDBc_theme_path){
		array_push($aDBc_themes_paths_array, str_replace('\\' ,'/', $aDBc_theme_path));
	}

	foreach($aDBc_themes_paths_array as $aDBc_theme_path){
		if(strpos($full_path, $aDBc_theme_path) !== false){
			$aDBc_path = str_replace($aDBc_theme_path."/", "", $full_path);
			$theme_name = explode("/", $aDBc_path, 2);
			$owner_name_type[0] = $theme_name[0];
			$owner_name_type[1] = "t";
			return $owner_name_type;
		}
	}

	// If not a plugin and not a theme, then affect it to WP? Maybe later I should return the file name instead of affect it to WP
	$owner_name_type[0] = "w";
	$owner_name_type[1] = "w";
	return $owner_name_type;
}

/******************************************************************
* Create list of all php files in the wordpress installation
*******************************************************************/
function aDBc_refresh_and_create_php_files_paths(){

	// We start by deleting old file containing paths
	@unlink(ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/all_files_paths.txt");
	
	// For every none categorized table/option/task, create all php files paths starting from ADBC_ABSPATH
	$myfile = fopen(ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/all_files_paths.txt", "a");
	aDBc_create_php_files_urls(ADBC_ABSPATH, $myfile);

	// Search also in WP-content if it is outside ADBC_ABSPATH
	if(is_dir(ADBC_WP_CONTENT_DIR_PATH)){
		if(strpos(ADBC_WP_CONTENT_DIR_PATH, ADBC_ABSPATH) === false){
			aDBc_create_php_files_urls(ADBC_WP_CONTENT_DIR_PATH, $myfile);
		}
	}

	// Search also in MU must use plugins if it is outside ADBC_ABSPATH and ADBC_WP_CONTENT_DIR_PATH
	if(is_dir(ADBC_WPMU_PLUGIN_DIR_PATH)){
		if(strpos(ADBC_WPMU_PLUGIN_DIR_PATH, ADBC_ABSPATH) === false && strpos(ADBC_WPMU_PLUGIN_DIR_PATH, ADBC_WP_CONTENT_DIR_PATH) === false){
			aDBc_create_php_files_urls(ADBC_WPMU_PLUGIN_DIR_PATH, $myfile);
		}
	}

	// Search in plugins directory if it is outside ADBC_WP_CONTENT_DIR_PATH and ADBC_ABSPATH
	if(is_dir(ADBC_WP_PLUGINS_DIR_PATH)){
		if(strpos(ADBC_WP_PLUGINS_DIR_PATH, ADBC_ABSPATH) === false && strpos(ADBC_WP_PLUGINS_DIR_PATH, ADBC_WP_CONTENT_DIR_PATH) === false){
			aDBc_create_php_files_urls(ADBC_WP_PLUGINS_DIR_PATH, $myfile);
		}
	}

	// Search in themes directories if they are outside ADBC_WP_CONTENT_DIR_PATH and ADBC_ABSPATH
	global $wp_theme_directories;
	foreach($wp_theme_directories as $aDBc_theme_path){
		$path = str_replace('\\' ,'/', $aDBc_theme_path);
		if(is_dir($path)){
			if(strpos($path, ADBC_ABSPATH) === false && strpos($path, ADBC_WP_CONTENT_DIR_PATH) === false){
				aDBc_create_php_files_urls(ADBC_WP_PLUGINS_DIR_PATH, $myfile);
			}
		}
	}

	fclose($myfile);

}

/******************************************************************
* Create list of all php files starting from the path in parameter
* $path_to_start_from : path to start searching from
* $myfile is the file where to save files paths
******************************************************************/
function aDBc_create_php_files_urls($path_to_start_from, $myfile){

		$aDBc_fp = opendir($path_to_start_from);

		while($aDBc_f = readdir($aDBc_fp)){

			// Ignore symbolic links
			if(preg_match("#^\.+$#", $aDBc_f)){
				continue;
			}

			// Create the full path for the current file/folder
			$full_path = $path_to_start_from . "/" . $aDBc_f;

			// If the current path is a folder, then call recursive function
			if(is_dir($full_path)) {

				// Skip upload directory while searching
				if(strpos($full_path, ADBC_UPLOAD_DIR_PATH) !== false){
					continue;
				}
				aDBc_create_php_files_urls($full_path, $myfile);

			}else{

				// Ignore all files that are not php
				if(strpos($aDBc_f, ".php") === false){
					continue;
				}

				// Save the file URL
				fwrite($myfile, str_replace('\\' ,'/', $full_path) . "\n");
				global $aDBc_total_files;
				$aDBc_total_files++;

			}
		}
}

/*************************************************************************************************************
* This functions refreshes the categorization file after delete process to keep only valid entries in the file
*************************************************************************************************************/
function aDBc_refresh_categorization_file_after_delete($names_deleted, $items_type){

	// Get the file path
	$path_file_categorization = ADBC_UPLOAD_DIR_PATH_TO_ADBC . "/" . $items_type . ".txt";

	// Test if there are any items that have been deleted to prevent waisting time && moreover the file exists
	if(count($names_deleted) > 0 && file_exists($path_file_categorization)){

		$file_categorization = fopen($path_file_categorization, "r");
	
		// Prepare an array containing new file info
		$array_new_file = array();

		// Count total lines in file
		$total_lines = 0;

		while(($item = fgets($file_categorization)) !== false){
			$total_lines++;
			$item_name = explode(":", trim($item), 2);
			$item_name = str_replace("+=+", ":", $item_name[0]);
			if(!in_array($item_name, $names_deleted)){
				array_push($array_new_file, trim($item));
			}
		}
		fclose($file_categorization);

		// We will refresh the file only if the number of new lines is lover than number of old files. To prevent refreshing the file when deleting items not existing in file
		if(count($array_new_file) < $total_lines){
			// Delete old file
			@unlink($path_file_categorization);

			// Create a new file which will hold new info
			$file_categorization = fopen($path_file_categorization, "a");

			foreach($array_new_file as $aDBc_item){
				fwrite($file_categorization, $aDBc_item . "\n");
			}
			fclose($file_categorization);
		}
	}
}



?>