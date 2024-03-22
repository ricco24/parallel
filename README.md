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
$parallel = new \Parallel\Parallel(__DIR__, 'parallel', 5, 0.1);

// Add some tasks (only examples, not part of parallel)
// Task is defined by its name and by dependencies (optional)
// Dependencies ($runAfter = []) are tasks which have to be done before task can start
$parallel->addTask(new \Parallel\AdminsTask('task:admin'), ['task:categories']);
$parallel->addTask(new \Parallel\UsersTask('task:user'));
$parallel->addTask(new \Parallel\ArticlesTask('task:articles'), ['task:admin', 'task:user']);
$parallel->addTask(new \Parallel\CategoriesTask('task:categories'));

// Some tasks can be too much resource expensive, so we can define how many tasks can run along this task.
// If we setup 0, this task will be run alone although we setup 5 as global max concurrent
$parallel->addTask(new \Parallel\ArticleCategoriesTask('task:articlesCategories'), 0);

// You can add Multiple tasks (one task which can be run parallel with different options/identifier)
//   You can use "%" in task name as placeholder for identifier. eg. "task:users-%:save". If placeholder missing identifier will be append as ":%" to end of task name (suffix). 
//   Task is defined by a) count or b) array of ids and its name, optional you can add dependencies
//   Multiple task must implementing MultipleTask interface.
// Dependencies ($runAfter = []) are tasks which have to be done before task can start.
//   Original task name will be automatically expanded to all multiple tasks names.
$parallel->addMultiTask(10, new \Parallel\UsersTask('task:user')); // add ['task:user:1', 'task:user:2'...'task:user:10']
$parallel->addTask(new \Parallel\UsersTask('task:user-after'), ['task:user']); // task 'task:user-after' will be running after  ['task:user:1', 'task:user:2'...'task:user:10']
$parallel->addMultiTask(['thumbs', 'logos'], new \Parallel\ImagesTask('task:images-%')); // add ['task:images-thumbs', 'task:images-logos'], new ImagesTask('task:images-thumbs', ...) will be called with ->setTaskIdentifier('thumbs') and ->setTaskCount(2)

// Run symfony application under hood
$parallel->runConsoleApp();

```

Now simply run
```sh
php command.php parallel:run
```

### Run only subnet of registered tasks
Sometimes you want to run only subnet of registered task (eg. in development). For this purpose use ```--subnet``` option in ```parallel:run``` command. 
--subnet option is validated as regexp and accept multiple values. **Also all dependencies that doesn't match any of subnet regexp is removed from matched tasks.**
 
```sh
# This command run only task:user and task:categories tasks
php command.php parallel:run --subnet task:user$ --subnet task:categories$
```

### Logging

PSR logger is implemented. So we can use ```monolog/monolog```.

```php
<?php
// Setup PSR logger
// ...
$parallel->setLogger($psrLogger);
```

### Analyze command
Parallel can visualize tasks dependencies graph. All you have to do is set analyze dir. Output file in HTML format will be generated to setup directory.
```php
<?php
$parallel->setAnalyzeDir(__DIR__ . '/../log');
```

```sh
# Now you can run
php command.php analyze:graph
```

## Tasks
Every task has to return some kind of result (SuccessResult, SkipResult, ErrorResult) as the result of task/item processing.

### Task types

#### SimpleTask

Suitable for tasks with static input data processing.

```php
<?php
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
<?php
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
<?php
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
