# Change log

## [Unreleased][unreleased]

## 2.1.0 - 2021-11-02
#### Added
- TaskLogger

## 2.0.0 - 2020-05-13
### Added
- MaxConcurrentTaskCount option for StackedTask
- List of task which ran with done tasks
- Tests
- Added sendMessage() method to ProgressTask and BatchProgressTask
- Check dependencies name before start
- Subnet option to run command

### Changed
- Custom log replaced with PSR log interfaces
- Composer dependencies update
- PrgressTask, BatchProgressTask first fetch items count and then gather items to process

### Fixed
- TableOutput show minimal saved time as 0
- Icons fix
- BatchProgressTask startup/shutdown error notify
- Unhandled exceptions in ProgressTask and BatchProgressTask
- DataHelper gigabytes count

## 1.0.0 - 2016-06-06
- First tagged version

[unreleased]: https://github.com/ricco24/parallel/compare/2.1.0...HEAD
[2.1.0]: https://github.com/ricco24/parallel/compare/2.0.0...2.1.0
[2.0.0]: https://github.com/ricco24/parallel/compare/1.0.0...2.0.0
[1.0.0]: https://github.com/ricco24/parallel/compare/984a8b517355aacb21db72f2750e699ddb49d280...1.0.0
