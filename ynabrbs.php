<?php
date_default_timezone_set('Europe/London');
// DATA ////////////////
$ynab_headers = [
	'date' => 'Date',
	'payee' => 'Payee',
	'category' => 'Category',
	'memo' => 'Memo',
	'outflow' => 'Outflow',
	'inflow' => 'Inflow'
];
$rbs_headers = [
	'date' => 'Date',
	'type' => 'Type',
	'description' => 'Description',
	'value' => 'Value',
	'balance' => 'Balance',
	'account-name' => 'Account Name',
	'account-number' => 'Account Number'
];
$rules = [
	'unknown' => [
		'regex' => '/$^/',
		'data' => [
			'payee' => 'XXXX FIXME XXXX',
			'category' => 'XXXX FIXME XXXX'
		]
	],
	'tesco-sb' => [
		'regex' => '/TESCO STORES.+ACTON/',
		'data' => [
			'payee' => 'Tesco (Shepherds Bush)',
			'category' => 'Everyday Expenses: Groceries',
			'memo' => 'Lunch'
		]
	],
	'tesco-kx' => [
		'regex' => '/TESCO STORES.+LONDON/',
		'data' => [
			'payee' => 'Tesco (Kings Cross)',
			'category' => 'Everyday Expenses: Groceries',
			'memo' => ''
		]
	],
	'gousto' => [
		'regex' => '/GOUSTO\.CO\.UK/',
		'data' => [
			'payee' => 'Gousto',
			'category' => 'Everyday Expenses: Groceries',
			'memo' => 'Gousto box'
		]
	],
	'atm' => [
		'regex' => '/(?=a)b/',
		'data' => [
			'payee' => 'ATM',
			'category' => 'Fun: Spending Money',
			'memo' => ''
		]
	],
	'amazon' => [
		'regex' => '/AMAZON UK RETAIL/',
		'data' => [
			'payee' => 'Amazon',
			'category' => 'Fun: Technology',
			'memo' => 'XXXX LOOK ME UP ON AMAZON.CO.UK XXXX'
		]
	],
	'o2' => [
		'regex' => '/O2/',
		'data' => [
			'payee' => 'O2',
			'category' => 'Monthly Bills: Phone (O2)',
			'memo' => ''
		]
	],
	'spotify' => [
		'regex' => '/SPOTIFYUK/',
		'data' => [
			'payee' => 'Spotify',
			'category' => 'Fun: Technology',
			'memo' => 'Monthly subscription'
		]
	],
	'rent' => [
		'regex' => '/CRISTI-RENT/',
		'data' => [
			'payee' => 'Rent',
			'category' => 'Monthly Bills: Rent',
			'memo' => ''
		]
	],
	'ndcs' => [
		'regex' => '/NDCS/',
		'data' => [
			'payee' => 'NDCS',
			'category' => 'Giving: NDCS',
			'memo' => ''
		]
	],
	'tfl' => [
		'regex' => '/LUL TICKET MACHINE/',
		'data' => [
			'payee' => 'TFL',
			'category' => 'Everyday Expenses: Travel',
			'memo' => ''
		]
	],
	'sky-cristi' => [
		'regex' => '/COBZARENCO CC.+SKY/',
		'data' => [
			'payee' => 'Sky Digital',
			'category' => 'Monthly Bills: Broadband (BT)',
			'memo' => 'Cristi'
		]
	],
	'sky' => [
		'regex' => '/SKY DIGITAL/',
		'data' => [
			'payee' => 'Sky Digital',
			'category' => 'Monthly Bills: Broadband (BT)',
			'memo' => ''
		]
	],
	'tommy-flynns' => [
		'regex' => '/TOMMY FLYNNS/',
		'data' => [
			'payee' => 'Pub',
			'category' => 'Fun: Social',
			'memo' => 'Tommy\'s'
		]
	]
];
/////////////////////

/////////////////////
// Main program loop
/////////////////////
if(count($argv) < 2){
	echo "Not enough arguments, usage: \"php $argv[0] <filname.csv>\"\n";
	return null;
}
echo "Converting \"$argv[1]\"\n";

$filename = $argv[1];

if(!is_readable(__DIR__ . '/' . $filename)) {
	echo "ERROR: Failed to open " . $filename . "\n";
	return null;
}

$csv_string = trim(file_get_contents(__DIR__ . '/' . $filename));
$rows = str_getcsv($csv_string, "\n");
$labels = array_map(function($val){
	return trim($val);
}, str_getcsv(array_shift($rows)));

$count = 0;
$data = [];
foreach ($rows as &$row) {
	if(empty($row)){
		continue;
	}
	$row_data = str_getcsv($row);
	if(count($row_data) > count($labels)){
		$row_data = array_slice($row_data, 0, count($labels));
	}
	if(count($row_data) < count($labels)){
		$diff = count($labels) - count($row_data);
		$row_data = array_pad($row_data, $diff, '');
	}
	$row = array_combine($labels, $row_data);

	$data[] = getYnabRow($row);
	$count++;
}
echo "Finished reading file\n";
echo "Number of lines read: " . $count . "\n";

$output_filename = 'output-' . date("Y-m-d-Hm") . '.csv';
$fp = fopen($output_filename, 'w');

fputcsv($fp, $ynab_headers);

foreach ($data as $line) {
	fputcsv($fp, $line);
}

fclose($fp);

echo "Result written to \"$output_filename\"\n";
/////////////////////
// End main program loop
/////////////////////

function getYnabRow(array $row)
{
	global $ynab_headers, $rbs_headers, $categories, $rules;

	$ynab_row = $ynab_headers;
	foreach ($ynab_row as $key => $value) {
		$ynab_row[$key] = '';
	}
	foreach ($row as $key => $value) {
		switch($key) {
			case $rbs_headers['date']:
				$ynab_row['date'] = trim($value);
				break;
			case $rbs_headers['type']:
				if($value === 'C/L'){
					$ynab_row['category'] = empty($ynab_row['category']) ?
						$rules['atm']['data']['category'] : $ynab_row['category'];
					$ynab_row['payee'] = empty($ynab_row['payee']) ?
						$rules['atm']['data']['payee'] : $ynab_row['payee'];
				}
				break;
			case $rbs_headers['description']:
				$ynab_data = getYnabData($value);
				$ynab_row['category'] = empty($ynab_row['category']) ?
					$ynab_data['category'] : $ynab_row['category'];
				$ynab_row['payee'] = empty($ynab_row['payee']) ?
					$ynab_data['payee'] : $ynab_row['payee'];
				$ynab_row['memo'] = empty($ynab_row['memo']) ?
					isset($ynab_data['memo']) ? $ynab_data['memo'] : trim($value) :
					$ynab_row['memo'];
				break;
			case $rbs_headers['value']:
				if(bccomp($value, 0, 2) < 0){
					$ynab_row['outflow'] = bcmul(abs($value), 1, 2);
				} else {
					$ynab_row['inflow'] = bcmul(abs($value), 1, 2);
				}
				break;
		}
	}
	return $ynab_row;
}

function getYnabData($data)
{
	global $rules;

	foreach($rules as $key => $payee){
		if(preg_match($payee['regex'], $data)){
			return $payee['data'];
		}
	}
	return [
		'payee' => '',
		'category' => ''
	];
}
