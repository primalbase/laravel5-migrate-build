<?php namespace Primalbase\Migrate\Console\Commands;

use Illuminate\Console\Command;
use Primalbase\Migrate\Builder;

class MigrateBuild extends Command
{
  protected $signature = 'migrate:build {tables?*} {--all}';

  public function handle()
  {
    $tables = $this->argument('tables');
    $isAll  = $this->option('all');

    try {
      $builder = new Builder();
      $availableTables = $builder->getTables();
      if ($isAll)
      {
        $tables = $availableTables;
        $this->info(implode(PHP_EOL, $tables));
        if (!$this->confirm('Built all tables?'))
          return;
      }
      else
      {
        if (empty($tables))
        {
          $this->error($this->getSynopsis());
          echo PHP_EOL;
          $this->comment('Listing tables.');
          $this->info(implode(PHP_EOL, $availableTables));
          $this->error('Select any table.  -- But not implemented yet.');
          return;
        }
      }

      foreach ($tables as $table)
      {
        if ($builder->exists($table))
        {
          if (!$this->confirm($table.' migration file already exists. Overwrite?'))
            continue;
        }
        $raw = $builder->make($table);
        $this->info($raw);
      }

    } catch (\Exception $e) {
      $this->error($e->getMessage());
    }
  }
}