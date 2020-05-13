# Parallel

Library for parallel (concurrent) task processing. Implemented with symfony/process.

**Basic table output**

![output](https://user-images.githubusercontent.com/1409647/81825294-c428c400-9536-11ea-92d9-5e227291c58a.gif)

## Configuration

### Basic
command.php
```php
#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

// First setup Parallel class
// If you setup log dir (optional) parallel will automatically create sub folder /stats and log running statistics in json format here.
$parallel = new \Parallel\Parallel(__DIR__, 'parallel', 5, __DIR__ . '/../log');

// Add some tasks (only examples, not part of parallel)
// Task is defined by its name and by dependencies (optional)
// Dependencies ($runAfter = []) are tasks which have to be done before task can start
$parallel->addTask(new \Parallel\AdminsTask('task:admin'), ['task:categories']);
$parallel->addTask(new \Parallel\UsersTask('task:user'));
$parallel->addTask(new \Parallel\ArticlesTask('task:articles'), ['task:admin', 'task:user']);
$parallel->addTask(new \Parallel\CategoriesTask('task:categories'));

// Run symfony application under hood
$parallel->runConsoleApp();

```

Now simply run
```sh
php command.php parallel:run
```

### Run only subnet of registered tasks
Sometimes you want to run only subnet of registered task (eg. in development).

For this purpose use ```--subnet``` option in ```parallel:run``` command.
 
```sh
# This command run only task:user and task:categories tasks
php command.php parallel:run --subnet task:user$ --subnet task:categories$
```

--subnet option is validated as regexp and accept multiple values.
**Also all dependencies that doesn't match any of subnet regexp is removed from matched tasks.**

### Resource expensive tasks

Some tasks can be too much resource expensive, so we can define how many tasks can run along this task.
If we setup 0, this task will be run alone although we setup 5 as global max concurrent

```php
<?php
$parallel->addTask(new \Parallel\ArticleCategoriesTask('task:articlesCategories'), 0);
```

### Logging

PSR logging is implemented. So we can use ```monolog/monolog```.

```php
<?php
// Setup monolog logger
// ...
$parallel->setLogger($monologLogger);
```

### Analyze command
Parallel can visualize tasks dependencies graph. All you have to do is setup analyze dir.
```php
<?php
$parallel->setAnalyzeDir(__DIR__ . '/../log');
```

```sh
# Now you can run
php command.php analyze:graph
```

## Tasks
If you want to do something with Parallel you need to implement new task and register it to application.

### Task types

#### SimpleTask
Suitable for tasks with static input data processing.
```php
class ImplementedSimpleTask extends SimpleTask
{
    protected function processTask(InputInterface $input, OutputInterface $output): TaskResult
    {
        // Do some magic
        return new SuccessResult();
    }
}
```

#### ProgressTask
Suitable if you need to process each item from a medium dataset separately. All source items are
provided at once. In some cases it can be too expensive for memory (see BatchProgressTask).
```php

class ImplementedProgressTask extends ProgressTask
{
    protected function items(): iterable
    {
        return DB::table('users');
    }

    protected function itemsCount(): int
    {
        return DB::table('users')->count();
    }

    protected function processItem($item): TaskResult
    {
        // $item here is one record form users table
        // It can be anything what is provided by items() method (array, object ...)
        if (!$item['is_active']) {
            return new SkipResult();
        }
        
        file_put_contents('active_users', $item['id'] . "/n", FILE_APPEND | LOCK_EX);
        return SuccessResult();
    }
}
```

#### BatchProgressTask
Most advanced task. Suitable if you need to process each item from a **large** dataset separately.
Items can be provided and processed in batches.
```php
class ImplementedBatchProgressTask extends BatchProgressTask
{
    protected function startup(): void
    {
        // Here you can prepare data
    }

    protected function shutdown(): void
    {
        // Here you can run cleanup
    }

    protected function items(int $processed): iterable
    {
        // Fetch data (eg. from database) by 500 and use offset
        return DB::table('users')->limit(500)->offset($processed);
    }

    protected function itemsCount(): int
    {
        // Count ALL data that will be processed
        return DB::table('users')->count();
    }

    protected function processItem($item): TaskResult
    {
        // $item here is one record form users table
        if (!$item['is_active']) {
            return new SkipResult();
        }
        
        return new SuccessResult([
            'id' => $item['id'],
            'name' => $item['name']
        ]);
    }

    protected function batch(array $items): void
    {
        DB::table('active_users')->insert($items);
    }
}

```

### Messages
Anywhere in ```ProgressTask``` and ```BatchProgressTask``` can be send message to output with ```sendMessage($message)``` method.