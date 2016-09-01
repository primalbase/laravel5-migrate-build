<?php
/**
 * @var string $className
 * @var string $tableName
 * @var string $engine
 * @var string $rowFormat
 * @var array $columns
 *   string label
 *   string name
 *   string type
 *   number|null size
 *   mixed default
 *   boolean index
 *   boolean unique
 *   boolean nullable
 * @var boolean $increments
 * @var boolean $timestamps
 * @var boolean $publishes
 * @var boolean $softDeletes
 */
$code =<<<__PHP__
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ${className} extends Migration {

  public function up()
  {
    Schema::create('${tableName}', function (Blueprint \$table) {


__PHP__;
if ($increments)
  $code.= "      \$table->increments('id');".PHP_EOL;

foreach ($columns as $column)
{
  if ($column['type'] == 'stapler')
  {
    $code.= sprintf("      \$table->string('%s_file_name')->nullable();", $column['name']).PHP_EOL;
    $code.= sprintf("      \$table->integer('%s_file_size')->nullable();", $column['name']).PHP_EOL;
    $code.= sprintf("      \$table->string('%s_content_type')->nullable();", $column['name']).PHP_EOL;
    $code.= sprintf("      \$table->timestamp('%s_updated_at')->nullable();", $column['name']).PHP_EOL;
  }
  else
  {
    $code.= sprintf("      \$table->%s('%s'", $column['type'], $column['name']);
    if (isset($column['size']))
      $code.= sprintf(", %s)", $column['size']);
    else
      $code.= ")";
    if (isset($column['default']))
    {
      if (in_array($column['type'], ['integer', 'bigInteger', 'mediumInteger', 'tinyInteger', 'smallInteger', 'unsignedInteger', 'unsignedBigInteger', 'float', 'double', 'decimal', 'boolean']))
        $code.= sprintf("->default(%s)", $column['default']);
      else
        $code.= sprintf("->default('%s')", $column['default']);
    }
    if ($column['index'])
      $code.= "->index()";
    if ($column['unique'])
      $code.= "->unique()";
    if ($column['nullable'])
      $code.= "->nullable()";
    $code.=';'.PHP_EOL;
  }
}
if ($publishes)
  $code.= "      \$table->datetime('published_at')->nullable();".PHP_EOL;
if ($publishes)
  $code.= "      \$table->datetime('terminated_at')->nullable();".PHP_EOL;
if ($timestamps)
  $code.= "      \$table->timestamps();".PHP_EOL;
if ($softDeletes)
  $code.= "      \$table->softDeletes();".PHP_EOL;
if ($engine)
  $code.= sprintf("      \$table->engine = '%s';", $engine).PHP_EOL;

$code.= '    });'.PHP_EOL;

if ($rowFormat)
{
  $sql = "ALTER TABLE `${tableName}` ROW_FORMAT=${rowFormat};";
  $code.= sprintf("    DB::unprepared('%s');", $sql).PHP_EOL;
}
$code.= '  }'.PHP_EOL;
$code.=<<<__PHP__

  public function down()
  {
    Schema::dropIfExists('${tableName}');
  }
}
__PHP__;

echo $code;