<?php 
/* Fetches the data from an URL using CURL, passing in basic authentication username and password, carry out the processing as per requirements and encode the array for processing. */
                      


function fetch_data() {
    $data_url = 'https://tst-api.feeditback.com/exam.users';

    $username = 'dev_test_user';
    $password = 'V8(Zp7K9Ab94uRgmmx2gyuT.';

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $data_url);  
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_USERPWD, "$username:$password");
    $response = curl_exec($curl);
    $processing_json_data = json_decode($response, true);


    foreach ($processing_json_data as $key => $val) {
	    // remove latitude 
	    unset($processing_json_data[$key]['latitude']);
	    // remove longitude
	    unset($processing_json_data[$key]['longitude']);

        //$processing_json_data[$key]['email'] = password_hash(strtolower($val['email']), PASSWORD_DEFAULT);
    
        /* Replace the content of the email field with a hashed version, using the hash_value function. Commented out above line because using password_hash and password_verify can only match a hashed string as TRUE or FALSE and not partial? I can't figure out how to verify partial matches after strings are hashed.... is this actually possible?*/	
        $processing_json_data[$key]['email'] = hash_value(strtolower($val['email']));

	    $address = explode(',', $val['address']);

        //(preg_replace('/.{2}(.*)$/', '*', $v));
	    /* commented out above's attempt to performing the obfuscation using regex. Instead use obfuscate_address() */
        $obfuscated_address = implode(', ', array_map('obfuscate_address', $address));

        $processing_json_data[$key]['address'] = $obfuscated_address;
    }

    /* save the JSON file as users.json */
	file_put_contents('users.json', json_encode($processing_json_data));
}

/*query()
    - query should search the json data in users.json and echo out the first_name and last_name fields of matching users.
        - `$field` identifies the user field which should be inspected
        - `$value` represents the data to match against.
        - `$exact` indicates whether the field exactly matches $value (TRUE) or whether the result should be returned if the field contains any instance of $value.
    - Note that searches by email address should do so by comparing email hashes.
*/
function query($field, $value, $exact = true)
{
    $json_file = file_get_contents('users.json');
	$json_data = json_decode($json_file, true);

    /*$values = array_column($json_data, $field)
    $matched_key = array_search($value, $values);
    */
    /* easier to use foreach to identify multiple matches (if any), so commented out the above lines in trying to use array_column() and array_search() */

    /* use hash_value for comparison if the $field is email */
    if ($field == 'email') {
        $value = hash_value(strtolower($value));
    }

    foreach ($json_data as $key => $val) {
        /* using == instead of === because otherwise values like age is not going to match as they are passed in as strings */
        if ($val[$field] == $value || ($exact == false && in_array($value, explode(' ', $val[$field])))) {
            echo $val['first_name'] . ' ' . $val['last_name'] . ' matched on the ' . $field . ' provided. <br><br>';
        }
    }
}

/*
report()
    - A report should be generated and written to disk called users-report.json.
    - It should contain the following information in an appropriate format:
        - The full name of the users who were created first & last (according to the "created" datetime).
        - The most common favorite_colour used.
        - The average age of a user, to 2 decimal places
        - From the 'about' fields across all users, a breakdown of word occurrence.
*/
function report()
{
    $data = file_get_contents('users.json');

    $json_data = json_decode($data, true);

    /* initialize an empty array, this is the final array which will be populated with all the data specified in the requirements */
    $report_array = [];
    
    /* get the 'created' column values for processing */
    $created = array_column($json_data, 'created');

    /* getting the array key of the minimum and maximum values of the created column, so we can use the returned key to find the first and last created row. */
    $created_first_key = array_keys($created, min($created))[0];
    $created_last_key = array_keys($created, max($created))[0];

    /* using the keys returned from above, to be able to get the names of the firstly and lastly created users */
    $created_first_full_name = $json_data[$created_first_key]['first_name'] . ' ' . $json_data[$created_first_key]['last_name'];
    $created_last_full_name = $json_data[$created_last_key]['first_name'] . ' ' . $json_data[$created_last_key]['last_name'];

    /* assign the firstly and lastly created users into the report array */
    $report_array['user_created_first'] = $created_first_full_name;
    $report_array['user_created_last'] = $created_last_full_name;

    /* get the 'favorite_colour' column values for processing */
    $favourite_colour_column = array_column($json_data, 'favorite_colour');
    
    /* count the number of times a colour appears in the 'favorite_colour' column. */
    $favourite_colour_count = array_count_values($favourite_colour_column);

    /* get the array value from the returned key and value of the 'max' value that the colour appears */
    $favourite_colour = array_values(array_keys($favourite_colour_count, max($favourite_colour_count)));
    
    if (count($favourite_colour) > 1) {
        $favourite_colours = '';
        foreach ($favourite_colour as $colour) {
            $favourite_colours .= $colour . ',';
        }
        $favourite_colours = rtrim($favourite_colours, ',');
    }

    $report_array['favourite_colour'] = ($favourite_colours) ?? $favourite_colour[0];

    $age_column = array_column($json_data, 'age');
    $average_age = number_format(array_sum($age_column) / count($age_column), 2, '.');

    $report_array['average_age'] = $average_age;

    /*$about_column_words = array_map('count_column_words', array_column($json_data, 'first_name'), array_column($json_data, 'last_name'), array_column($json_data, 'about'));*/
    // Commented out above, going to do this one with a foreach loop instead because it is easier to display the end result within a foreach rather than displaying the whole result from count_column_words()
    $about_column_word_occurrence = [];
    foreach ($json_data as $key => $val) {
        array_push($about_column_word_occurrence, [
            'first_name' => $val['first_name'], 
            'last_name' => $val['last_name'],
            'count' => count_column_words($val['about'])
        ]);
    }

    $report_array['about_column_word_occurrence'] = $about_column_word_occurrence;

    /* create users-report.json */
    file_put_contents('users-report.json', json_encode($report_array)); 
    
}

/* return the number of words from the column passed in from the argument */
function count_column_words($column) {
    return count($number_of_words = explode(' ', $column));
}

function hash_value($value)
{
    return hash('sha256', $value);
}

/* the obfuscate_address function is for replacing the characters of the word passed in to an asterisk APART from the first 2 characters. */
function obfuscate_address($address) {

    // trim off any whitespaces at the ends
    $address = trim($address);
    $address_word = explode(' ', $address);
	$address_word_processed = '';	
	foreach ($address_word as $word) {
       $address_word_processed .= str_pad(substr($word, 0, 2), strlen($word), '*') . ' ';
	}
    // trim off the extra whitespace added to the last word of the address
	return rtrim($address_word_processed);
}

/* This is an assumption that the test case query('id', '5be5884a331b2c695', false); should return false? Because the value as a whole is not a partial / substring match  */

query('id', '5be5884a7ab109472363c6cd');
query('id', '5be5884a331b2c695', false);
query('id', '5be5884a331b24639s3cc695');
query('age', '22');
query('age', '20'); 
query('about', 'exa', false);
query('about', 'ace', false);
query('about', 'adipisicing', false);
query('email', 'mcconnellbranch@zytrek.com');
query('email', 'ryansand@xandem.com');
query('email', 'edwinachang', false);
report();
