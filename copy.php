<?php

use \Google\Service\Sheets;

function create_service($credentials)
{
    $client = new \Google_Client();
    $client->setApplicationName('TableIntoGSheet');
    $client->setScopes([Sheets::SPREADSHEETS]);
    $client->setAccessType('offline');
    $client->setAuthConfig($credentials ?? 'credentials.json');

    return new Sheets($client);
}

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
 * @param string|object|null $credentials Credentials file path, decoded associative json object, or null for ./credentials.json
 */
function appendToSheet($spreadsheetId, $pageName, $values, $maxRows, $credentials)
{
    //Reading data from spreadsheet.

    $service = create_service($credentials);
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
 * @param string|object|null $credentials Credentials file path, decoded associative json object, or null for ./credentials.json
 */
function copyDbToSheet($spreadsheetId, $dbHostname, $dbDb, $dbUser, $dbPassword, $table, $pageName, $credentials)
{

    //Reading data from spreadsheet.
    $service = create_service($credentials);

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

/**
 * @brief Mirrors the whole database $table into the spreadsheet.
 * Without column names or any extra steps.
 * 
 * @param string $spreadsheetId The long string after https://docs.google.com/spreadsheets/d/
 * @param int $firstRow
 * @param string $pageName Name of page in google sheet
 * @param array $dataArray
 * @param string|object|null $credentials Credentials file path, decoded associative json object, or null for ./credentials.json
 */
function copyArrayToSheet($spreadsheetId, $firstRow, $pageName, $dataArray, $credentials = null)
{
    $firstRow ??= 1;
    //Reading data from spreadsheet.
    $service = create_service($credentials);

    $colCount = count($dataArray[0]); //Count of columns in a row
    $rowCount = count($dataArray) + ($firstRow - 1);

    $colLetter = getNameFromNumber($colCount);
    $update_range = "$pageName!A$firstRow:$colLetter$rowCount";

    $body = new Sheets\ValueRange([
        'values' => $dataArray
    ]);

    $params = [
        'valueInputOption' => 'RAW'
    ];
    $update_sheet = $service->spreadsheets_values->update($spreadsheetId, $update_range, $body, $params);
    return $update_sheet;
}

/**
 * Clears all horizontal borders in the sheet and draws only a single horizontal border
 * @param string $spreadsheetId The long string after https://docs.google.com/spreadsheets/d/
 * @param int $firstRow
 * @param int $ruleRow At which row to draw the horizontal rule
 * @param string|object|null $credentials Credentials file path, decoded associative json object, or null for ./credentials.json
 */
function hRule($spreadsheetId, $firstRow = 1, $ruleRow = 1, $credentials = null)
{
    $service = create_service($credentials);
    $body = new Sheets\BatchUpdateSpreadsheetRequest([
        'requests' => [
            new Sheets\Request([
                "updateBorders"=> [
                    "range" => [
                        "startRowIndex" => $firstRow,
                        "endRowIndex" => $ruleRow,
                    ],
                    "bottom" => [
                        "style" => "DASHED",
                        "width" => 4,
                        "color" => [
                            "red" => 1.0
                        ],
                    ]
                ],
            ]),
            new Sheets\Request([
                "updateBorders"=> [
                    "range" => [
                        "startRowIndex" => $ruleRow,
                    ],
                    "innerHorizontal" => [
                        "style" => "NONE",
                    ]
                ],
            ])
        ]
    ]);

    return $service->spreadsheets->batchUpdate($spreadsheetId, $body);
}
