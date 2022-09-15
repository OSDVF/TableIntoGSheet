<?php
require_once 'vendor/autoload.php';

use \Google\Service\Sheets;

function get_safe_table($mysqli, $table)
{
    $query = "SELECT table_name FROM information_schema.TABLES WHERE table_name = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $result = $stmt->get_result();
    $result = $result->fetch_row();
    if ($result)
    {
        return $result[0];
    }
    else
    {
        exit;
    }
}

function getNameFromNumber($num)
{
    $numeric = ($num - 1) % 26;
    $letter = chr(65 + $numeric);
    $num2 = intval(($num - 1) / 26);
    if ($num2 > 0)
    {
        return getNameFromNumber($num2) . $letter;
    }
    else
    {
        return $letter;
    }
}

/**
 * @brief Append one row to a table
 * 
 * The table starts at A1 cell and can have maximum row count of $maxRows.
 * Column count is considered equal to \code count($values)\endcode
 * 
 * @param string $spreadsheetId The long string after https://docs.google.com/spreadsheets/d/
 * @param string $pageName Name of page in google sheet
 * @param array<array<string>> $values Array of rows. Each value in inner array is one column in a row
 * @param int $maxRows Maximum row count of the table. If unsure, set to 10000000.
 */
function appendToSheet($spreadsheetId, $pageName, $values, $maxRows)
{
    //Reading data from spreadsheet.
    $client = new \Google_Client();
    $client->setApplicationName('TableIntoGSheet');
    $client->setScopes([Sheets::SPREADSHEETS]);
    $client->setAccessType('offline');
    $client->setAuthConfig(__DIR__ . '/credentials.json');

    $service = new Sheets($client);
    $colLetter = getNameFromNumber(count($values));
    $update_range = "$pageName!A1:$colLetter$maxRows";

    $body = new Sheets\ValueRange([
        'values' => [$values]
    ]);

    $params = [
        'valueInputOption' => 'RAW'
    ];
    $update_sheet = $service->spreadsheets_values->append($spreadsheetId, $update_range, $body, $params);
    return $update_sheet;
}

/**
 * @brief Mirrors the whole database $table into the spreadsheet.
 * Without column names or any extra steps.
 * 
 * @param string $spreadsheetId The long string after https://docs.google.com/spreadsheets/d/
 * @param string $dbHostname
 * @param string $dbUser
 * @param string $dbPassword
 * @param string $table The SQL table to mirror
 * @param string $pageName Name of page in google sheet
 */
function copyDbToSheet($spreadsheetId, $dbHostname, $dbDb, $dbUser, $dbPassword, $table, $pageName)
{

    //Reading data from spreadsheet.
    $client = new \Google_Client();
    $client->setApplicationName('TableIntoGSheet');
    $client->setScopes([Sheets::SPREADSHEETS]);
    $client->setAccessType('offline');
    $client->setAuthConfig(__DIR__ . '/credentials.json');

    $service = new Sheets($client);

    //
    // MySQL setup
    //
    $database = new mysqli($dbHostname, $dbUser, $dbPassword, $dbDb);
    if ($database->connect_error)
    {
        return $database->connect_error;
    }
    $database->set_charset('utf8');

    $table_safe = get_safe_table($database, $table);

    $result = $database->query("SELECT * FROM " . $table_safe);

    $values = [];
    if ($result)
    {
        $rowCount = 0;
        $colCount = 0;
        while ($row = $result->fetch_row())
        {
            $colCount = max($colCount, count($row));
            $rowCount++;

            array_push($values, $row);
        }
    }
    else
    {
        return $database->error;
    }

    $colLetter = getNameFromNumber($colCount);
    $update_range = "$pageName!A1:$colLetter$rowCount";

    $body = new Sheets\ValueRange([
        'values' => $values
    ]);

    $params = [
        'valueInputOption' => 'RAW'
    ];
    $update_sheet = $service->spreadsheets_values->update($spreadsheetId, $update_range, $body, $params);
    return $update_sheet;
}

if (realpath(__FILE__) == realpath($_SERVER['DOCUMENT_ROOT'] . $_SERVER['SCRIPT_FILENAME']))
{
    // The script was run directly
    $shortopts = "i:h:d:u:p:t:s:m:a";

    $longopts  = array(
        "sheetId:",
        "hostname:",
        "db:",
        "user:",
        "password:",
        "table:",
        "page:",
        "max:",
        "append"
    );
    $restIndex;
    $options = getopt($shortopts, $longopts, $restIndex);
    if (!$options)
    {
        echo 'Parameters error';
        die;
    }

    if ((@$options['a'] ?? @$options['append']) === false)
    {
        var_dump(appendToSheet(
            @$_GET['sheet'] ?? @$options['i'] ?? $options['sheetId'],
            @getenv('page') ?? @$options['s'] ?? $options['page'],
            array_slice($argv, $restIndex),
            @$options['m'] ?? $options['max'] ?? 10000
        ));
    }
    else
    {
        var_dump(copyDbToSheet(
            @$_GET['sheet'] ?? @$options['i'] ?? $options['sheetId'],
            $options['h'] ?? @$options['hostname'] ?? @getenv('hostname'),
            @$options['d'] ?? @$options['db'] ?? @getenv('db'),
            @$options['u'] ?? @$options['user'] ?? @getenv('user'),
            @$options['p'] ?? @$options['password'] ?? @getenv('password'),
            @$options['table'] ?? @$options['t'] ?? @getenv('table'),
            @$options['s'] ?? $options['page'] ?? @getenv('page')
        ));
    }
}
