<?php namespace Primalbase\Migrate;

use Exception;
use Google_Client;
use Google_Service_Sheets;
use Google_Service_Sheets_Sheet;
use Google_Service_Sheets_SheetProperties;
use Google_Service_Sheets_Spreadsheet;
use Google_Service_Sheets_ValueRange;
use View;

class Builder
{
  protected $tables;
  protected $clientKeyPath;
  protected $spreadSheetId;
  protected $client;
  protected $sheetsService;
  /** @var Google_Service_Sheets_Spreadsheet */
  protected $spreadsheet;
  /** @var Google_Service_Sheets_Sheet[] */
  protected $sheets;
  protected $spreadsheetsValues;
  protected $availableSheetDefinition;
  protected $creates;

  public function __construct()
  {
    $this->clientKeyPath = config('migrate-build.client_key_path');
    $this->spreadSheetId = config('migrate-build.spread_sheet_id');
    $this->availableSheetDefinition =
      config('migrate-build.available_sheet_definition');
    putenv('GOOGLE_APPLICATION_CREDENTIALS='.$this->clientKeyPath);
    $this->creates = 0;
  }

  protected function getClient()
  {
    if (!$this->client)
    {
      $client = new Google_Client();
      $client->useApplicationDefaultCredentials();
      $client->addScope(Google_Service_Sheets::SPREADSHEETS_READONLY);
      $client->setAccessType('offline');
      $this->client = $client;
    }
    return $this->client;
  }


  protected function getSheetsService()
  {
    if (!$this->sheetsService)
    {
      $client = $this->getClient();
      $this->sheetsService = new Google_Service_Sheets($client);
    }
    return $this->sheetsService;
  }

  public function getSpreadsheet()
  {
    if (!$this->spreadsheet)
    {
      $service = $this->getSheetsService();
      $spreadsheet = $service->spreadsheets->get($this->spreadSheetId);
      if (!$spreadsheet)
        throw new Exception($this->spreadSheetId.' is not exists.');
      $this->spreadsheet = $spreadsheet;
    }
    return $this->spreadsheet;
  }

  public function getSheets()
  {
    if (!$this->sheets)
    {
      $spreadsheet = $this->getSpreadsheet();
      $this->sheets = $spreadsheet->getSheets();
    }
    return $this->sheets;
  }

  public function getSpreadSheetsValues()
  {
    if (!$this->spreadsheetsValues)
    {
      $service = $this->getSheetsService();
      $this->spreadsheetsValues = $service->spreadsheets_values;
    }
    return $this->spreadsheetsValues;
  }

  public function getTables()
  {
    if (!$this->tables)
    {
      $sheets = $this->getSheets();
      $values = $this->getSpreadSheetsValues();
      $define = $this->availableSheetDefinition;
      $tables = [];
      $ranges = [];
      foreach ($sheets as $sheet)
      {
        /** @var Google_Service_Sheets_SheetProperties $properties */
        $properties = $sheet->getProperties();
        $title = $properties->title;
        $ranges[] = $title . '!' . $define['position'];
      }
      $response = $values->batchGet($this->spreadSheetId, [
        'ranges' => $ranges,
      ]);

      /** @var Google_Service_Sheets_ValueRange $dataSet */
      foreach ($response as $dataSet)
      {
        $value = array_get($dataSet->getValues(), '0.0');
        if ($value != $define['value'])
          continue;

        if (preg_match('/^(.+)!/u', $dataSet->getRange(), $m))
        {
          $tables[] = $m[1];
        }
      }

      $this->tables = $tables;
    }

    return $this->tables;
  }

  public function creates()
  {
    return $this->creates;
  }

  public function exists($table)
  {
    $pattern = base_path('database/migrations/????_??_??_??????_create_'.$table.'_table.php');
    return (count(glob($pattern)) > 0);
  }

  public function make($table, $saving = true)
  {
    $definition = $this->readTableDefinition($table);

    View::addNamespace('migrate-build', __DIR__.'/templates');

    $migration = view()->make('migrate-build::migration', [
      'className'   => $definition['className'],
      'tableName'   => $definition['tableName'],
      'engine'      => $definition['engine'],
      'rowFormat'   => $definition['rowFormat'],
      'increments'  => $definition['increments'],
      'timestamps'  => $definition['timestamps'],
      'publishes'   => $definition['publishes'],
      'softDeletes' => $definition['softDeletes'],
      'columns'     => $definition['columns'],
    ])->render();

    $pattern = $pattern = base_path('database/migrations/????_??_??_??????_create_'.$table.'_table.php');
    if (count($files = glob($pattern)) > 0)
    {
      $filePath = $files[0];
    }
    else
    {
      $filePath = base_path(sprintf('database/migrations/%s_create_%s_table.php', date('Y_m_d_His'), $table));
      $this->creates++;
    }

    if ($saving)
    {
      file_put_contents($filePath, $migration);
    }

    return $migration;
  }

  protected function readTableDefinition($table)
  {
    if (!in_array($table, $this->getTables()))
      throw new Exception($table.' not exists.');

    $ranges = [
      'increments'  => $table.'!E4',
      'timestamps'  => $table.'!J4',
      'publishes'   => $table.'!O4',  // publishes
      'softDeletes' => $table.'!T4',  // softDeletes
      'engine'      => $table.'!AC4', // engine
      'rowFormat'   => $table.'!AQ4', // rowFormat
    ];

    $response = $this->getSpreadSheetsValues()->batchGet($this->spreadSheetId, [
      'ranges' => array_values($ranges),
    ]);

    $definition = [
      'tableName' => $table,
    ];
    $keys = array_keys($ranges);
    foreach ($response->getValueRanges() as $i => $valueRange)
    {
      $definition[$keys[$i]] = array_get($valueRange->getValues(), '0.0');
    }

    $columns = [];
    $row = 7;
    while(true)
    {
      $ranges = [
        'no'       => $table.'!A'.$row,
        'label'    => $table.'!C'.$row,
        'name'     => $table.'!L'.$row,
        'type'     => $table.'!U'.$row,
        'size'     => $table.'!Z'.$row,
        'default'  => $table.'!AB'.$row,
        'index'    => $table.'!AE'.$row,
        'unique'   => $table.'!AG'.$row,
        'nullable' => $table.'!AI'.$row,
        'ignore'   => $table.'!AK'.$row,
      ];

      $response = $this->getSpreadSheetsValues()->batchGet($this->spreadSheetId, [
        'ranges' => array_values($ranges),
      ]);

      $valueRanges = $response->getValueRanges();
      $properties = [
        'no'       => $this->getCellNumber($valueRanges[0]),
        'label'    => $this->getCellString($valueRanges[1]),
        'name'     => $this->getCellString($valueRanges[2]),
        'type'     => $this->getCellString($valueRanges[3]),
        'size'     => $this->getCellNumber($valueRanges[4], '0.0', null),
        'default'  => $this->getCellValue($valueRanges[5]),
        'index'    => $this->getCellFlag($valueRanges[6]),
        'unique'   => $this->getCellFlag($valueRanges[7]),
        'nullable' => $this->getCellFlag($valueRanges[8]),
        'ignore'   => $this->getCellFlag($valueRanges[9]),
      ];

      if (!$properties['no'])
        break;

      if (!$properties['ignore'])
      {
        $columns[] = $properties;
      }
      $row++;
    }

    $definition['columns']   = $columns;
    $definition['className'] = sprintf("Create%sTable", studly_case($table));

    return $definition;
  }

  protected function getCellFlag(Google_Service_Sheets_ValueRange $valueRange, $position = '0.0', $default = false)
  {
    if (array_has($valueRange->getValues(), $position))
    {
      return (array_get($valueRange->getValues(), $position) == '○');
    }
    return $default;
  }

  protected function getCellString(Google_Service_Sheets_ValueRange $valueRange, $position = '0.0', $default = '')
  {
    if (array_has($valueRange->getValues(), $position))
    {
      return (string)array_get($valueRange->getValues(), $position);
    }
    return $default;
  }

  protected function getCellValue(Google_Service_Sheets_ValueRange $valueRange, $position = '0.0', $default = null)
  {
    if (array_has($valueRange->getValues(), $position))
    {
      return array_get($valueRange->getValues(), $position);
    }
    return $default;
  }

  protected function getCellNumber(Google_Service_Sheets_ValueRange $valueRange, $position = '0.0', $default = 0)
  {
    if (array_has($valueRange->getValues(), $position))
    {
      return floatval(array_get($valueRange->getValues(), $position));
    }
    return $default;
  }

}